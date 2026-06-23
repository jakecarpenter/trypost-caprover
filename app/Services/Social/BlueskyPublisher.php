<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Enums\SocialAccount\Platform;
use App\Exceptions\Social\BlueskyPublishException;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Services\Media\MediaOptimizer;
use App\Services\Social\Concerns\HasSocialHttpClient;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class BlueskyPublisher
{
    use HasSocialHttpClient;

    /** Times to retry a transiently-failing video transcode before giving up. */
    private const VIDEO_UPLOAD_ATTEMPTS = 3;

    public function publish(PostPlatform $postPlatform): array
    {
        $this->validateContentLength($postPlatform);

        $content = $postPlatform->post->content ? app(ContentSanitizer::class)->sanitize($postPlatform->post->content, $postPlatform->platform) : null;

        $account = $postPlatform->socialAccount;
        $service = $account->meta['service'] ?? config('trypost.platforms.bluesky.default_service');

        // Refresh token if needed
        if ($account->is_token_expired || $account->is_token_expiring_soon) {
            app(ConnectionVerifier::class)->refreshToken($account);
        }

        $medias = $postPlatform->post->mediaItems;
        $embed = null;

        // Upload images if present (max 4)
        if ($medias->count() > 0) {
            $images = [];
            foreach ($medias->take(4) as $media) {
                if ($media->isImage()) {
                    $blob = $this->uploadBlob($account, $service, $media->url, $media->mime_type);
                    if ($blob) {
                        $images[] = [
                            'alt' => '',
                            'image' => $blob,
                        ];
                    }
                }
            }

            if (count($images) > 0) {
                $embed = [
                    '$type' => BlueskyLexicon::EMBED_IMAGES,
                    'images' => $images,
                ];
            }
        }

        // A post carries either images or a single video, never both. Only look
        // for a video when no image embed was built (mirrors the official client).
        if ($embed === null) {
            $video = $medias->first(fn ($media) => $media->isVideo());

            if ($video) {
                $videoBlob = $this->uploadVideo($account, $service, $video->url);

                if ($videoBlob) {
                    $embed = [
                        '$type' => BlueskyLexicon::EMBED_VIDEO,
                        'video' => $videoBlob,
                    ];
                }
            }
        }

        // Parse facets (links, mentions, hashtags) from text
        $text = $content ?? '';
        $facets = $this->parseFacets($text);

        // Create post record
        $record = [
            '$type' => BlueskyLexicon::FEED_POST,
            'text' => $text,
            'createdAt' => now()->toIso8601ZuluString(),
        ];

        if ($embed) {
            $record['embed'] = $embed;
        }

        if (! empty($facets)) {
            $record['facets'] = $facets;
        }

        $response = $this->socialHttp()->withToken($account->access_token)
            ->post("{$service}/xrpc/".BlueskyLexicon::CREATE_RECORD, [
                'repo' => $account->platform_user_id,
                'collection' => BlueskyLexicon::FEED_POST,
                'record' => $record,
            ]);

        if ($response->failed()) {
            Log::error('Bluesky post failed', [
                'status' => $response->status(),
                'body' => $this->redactResponseBody($response->body()),
            ]);

            $this->handleApiError($response);
        }

        $data = $response->json();

        // Extract post ID from URI (at://did/app.bsky.feed.post/xxx)
        $uri = data_get($data, 'uri');
        $postId = basename($uri);

        return [
            'id' => $postId,
            'url' => $this->buildPostUrl($account->username, $postId),
        ];
    }

    private function uploadBlob(SocialAccount $account, string $service, string $url, string $mimeType): ?array
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'bsky_blob_');

        try {
            $downloadResponse = Http::withOptions(['sink' => $tempFile])->timeout(600)->get($url);

            if ($downloadResponse->failed()) {
                throw new Exception('Failed to download media: HTTP '.$downloadResponse->status());
            }

            $fileSize = filesize($tempFile);

            if ($fileSize === false || $fileSize === 0) {
                Log::error('Bluesky failed to download media', ['url' => $url]);

                return null;
            }

            // Optimize images for Bluesky's 1MB limit
            if (str_starts_with($mimeType, 'image/') && ! str_starts_with($mimeType, 'image/gif')) {
                $optimizer = app(MediaOptimizer::class);
                $optimizedPath = $optimizer->optimizeImage($tempFile, Platform::Bluesky);
                @unlink($tempFile);
                $tempFile = $optimizedPath;
                $mimeType = 'image/jpeg';
            }

            $stream = fopen($tempFile, 'r');

            $response = $this->socialHttp()->withToken($account->access_token)
                ->withHeaders(['Content-Type' => $mimeType])
                ->withBody($stream, $mimeType)
                ->post("{$service}/xrpc/".BlueskyLexicon::UPLOAD_BLOB);

            if (is_resource($stream)) {
                fclose($stream);
            }

            if ($response->failed()) {
                Log::error('Bluesky blob upload failed', [
                    'status' => $response->status(),
                    'body' => $this->redactResponseBody($response->body()),
                ]);

                return null;
            }

            return data_get($response->json(), 'blob');
        } catch (Exception $e) {
            Log::error('Bluesky blob upload exception', [
                'error' => $e->getMessage(),
                'url' => $url,
            ]);

            return null;
        } finally {
            @unlink($tempFile);
        }
    }

    /**
     * Upload a video to Bluesky and return the processed blob for embedding.
     *
     * Unlike images, video does not go to the PDS via uploadBlob. It is sent to
     * the separate video service (video.bsky.app), which transcodes it and
     * stores the resulting blob on the account's PDS. The flow is:
     *   1. resolve the account's real PDS host (for the service-auth audience),
     *   2. mint a service-auth token scoped to uploadBlob,
     *   3. POST the bytes to app.bsky.video.uploadVideo,
     *   4. poll app.bsky.video.getJobStatus until the blob is ready.
     *
     * Returns null on any failure so the post still publishes as text rather
     * than crashing the whole job (mirrors uploadBlob()).
     */
    private function uploadVideo(SocialAccount $account, string $service, string $url): ?array
    {
        $videoService = (string) config('trypost.platforms.bluesky.video_service');
        $did = (string) $account->platform_user_id;

        $tempFile = tempnam(sys_get_temp_dir(), 'bsky_video_');

        if ($tempFile === false) {
            Log::error('Bluesky could not create temp file for video upload', ['url' => $url]);

            return null;
        }

        try {
            $downloadResponse = Http::withOptions(['sink' => $tempFile])->timeout(600)->get($url);

            if ($downloadResponse->failed()) {
                throw new Exception('Failed to download video: HTTP '.$downloadResponse->status());
            }

            $fileSize = filesize($tempFile);

            if ($fileSize === false || $fileSize === 0) {
                Log::error('Bluesky failed to download video', ['url' => $url]);

                return null;
            }

            // Bluesky caps videos at 100MB; skip oversized files rather than
            // burning an upload that the service will reject.
            if ($fileSize > 100 * 1024 * 1024) {
                Log::error('Bluesky video exceeds 100MB limit', ['url' => $url, 'size' => $fileSize]);

                return null;
            }

            $pds = $this->resolvePdsEndpoint($account, $service);
            $pdsHost = parse_url($pds, PHP_URL_HOST);

            if (! is_string($pdsHost) || $pdsHost === '') {
                Log::error('Bluesky could not resolve PDS host for video upload', ['did' => $did]);

                return null;
            }

            // The upload token is consumed by the video service to write the
            // blob back to the user's PDS, so its audience is the PDS itself.
            // Minted once (valid 30 min) and reused across retries below.
            $uploadToken = $this->getServiceAuth($account, $pds, "did:web:{$pdsHost}", BlueskyLexicon::UPLOAD_BLOB);

            if ($uploadToken === null) {
                return null;
            }

            // The transcoder occasionally fails a job transiently
            // (JOB_STATE_FAILED "Failed to process video") even for valid input,
            // so retry the upload+poll a couple of times before giving up.
            for ($attempt = 1; $attempt <= self::VIDEO_UPLOAD_ATTEMPTS; $attempt++) {
                $blob = $this->attemptVideoUpload($account, $pds, $videoService, $did, $uploadToken, $tempFile);

                if ($blob !== null) {
                    return $blob;
                }

                if ($attempt < self::VIDEO_UPLOAD_ATTEMPTS) {
                    Log::warning('Bluesky video upload attempt failed, retrying', [
                        'attempt' => $attempt,
                        'url' => $url,
                    ]);
                }
            }

            return null;
        } catch (Exception $e) {
            Log::error('Bluesky video upload exception', [
                'error' => $e->getMessage(),
                'url' => $url,
            ]);

            return null;
        } finally {
            if (is_string($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    /**
     * A single upload-and-poll attempt against the video service. Returns the
     * processed blob, or null if this attempt failed (so the caller can retry).
     */
    private function attemptVideoUpload(SocialAccount $account, string $pds, string $videoService, string $did, string $uploadToken, string $tempFile): ?array
    {
        $stream = fopen($tempFile, 'r');

        if ($stream === false) {
            Log::error('Bluesky could not open video file for upload', ['file' => $tempFile]);

            return null;
        }

        $name = bin2hex(random_bytes(8)).'.mp4';
        $uploadUrl = "{$videoService}/xrpc/".BlueskyLexicon::VIDEO_UPLOAD
            .'?did='.rawurlencode($did).'&name='.rawurlencode($name);

        $response = $this->socialHttp()->withToken($uploadToken)
            ->withHeaders(['Content-Type' => 'video/mp4'])
            ->withBody($stream, 'video/mp4')
            ->post($uploadUrl);

        if (is_resource($stream)) {
            fclose($stream);
        }

        // uploadVideo returns the jobStatus object directly (top-level),
        // while getJobStatus wraps it under a `jobStatus` key — accept both.
        $body = $response->json();
        $jobStatus = data_get($body, 'jobStatus') ?: $body;

        // A re-upload of identical bytes returns 409 with the already
        // finished job, whose blob we can embed directly.
        if ($response->failed() && $response->status() !== 409) {
            Log::error('Bluesky video upload failed', [
                'status' => $response->status(),
                'body' => $this->redactResponseBody($response->body()),
            ]);

            return null;
        }

        if (data_get($jobStatus, 'blob')) {
            return data_get($jobStatus, 'blob');
        }

        $jobId = data_get($jobStatus, 'jobId');

        if (! is_string($jobId) || $jobId === '') {
            Log::error('Bluesky video upload returned no jobId', [
                'body' => $this->redactResponseBody($response->body()),
            ]);

            return null;
        }

        return $this->pollVideoJob($account, $pds, $videoService, $jobId);
    }

    /**
     * Poll the video service until the transcode job finishes, then return its
     * blob. Returns null if the job fails or never completes in time.
     */
    private function pollVideoJob(SocialAccount $account, string $pds, string $videoService, string $jobId): ?array
    {
        $videoServiceDid = (string) config('trypost.platforms.bluesky.video_service_did');
        $jobToken = $this->getServiceAuth($account, $pds, $videoServiceDid, BlueskyLexicon::VIDEO_GET_JOB_STATUS);

        if ($jobToken === null) {
            return null;
        }

        $statusUrl = "{$videoService}/xrpc/".BlueskyLexicon::VIDEO_GET_JOB_STATUS;

        // Up to ~5 minutes; processing usually finishes within seconds. State is
        // checked before sleeping so an already-complete job returns at once.
        for ($attempt = 0; $attempt < 150; $attempt++) {
            $response = $this->socialHttp()->withToken($jobToken)
                ->get($statusUrl, ['jobId' => $jobId]);

            // Bail on a hard error (e.g. expired/invalid token) instead of
            // sleeping to the timeout and masking the real failure.
            if ($response->failed()) {
                Log::error('Bluesky getJobStatus failed', [
                    'jobId' => $jobId,
                    'status' => $response->status(),
                    'body' => $this->redactResponseBody($response->body()),
                ]);

                return null;
            }

            $body = $response->json();
            $jobStatus = data_get($body, 'jobStatus') ?: $body;
            $state = data_get($jobStatus, 'state');

            if ($state === 'JOB_STATE_COMPLETED' && data_get($jobStatus, 'blob')) {
                return data_get($jobStatus, 'blob');
            }

            if ($state === 'JOB_STATE_FAILED') {
                Log::error('Bluesky video processing failed', [
                    'jobId' => $jobId,
                    'message' => data_get($jobStatus, 'message'),
                ]);

                return null;
            }

            sleep(2);
        }

        Log::error('Bluesky video processing timed out', ['jobId' => $jobId]);

        return null;
    }

    /**
     * Mint a short-lived service-auth token (com.atproto.server.getServiceAuth)
     * scoped to a single audience + method, used to authorize the video service.
     */
    private function getServiceAuth(SocialAccount $account, string $pds, string $aud, string $lxm): ?string
    {
        try {
            $response = $this->socialHttp()->withToken($account->access_token)
                ->get("{$pds}/xrpc/".BlueskyLexicon::GET_SERVICE_AUTH, [
                    'aud' => $aud,
                    'lxm' => $lxm,
                    'exp' => now()->addMinutes(30)->timestamp,
                ]);

            if ($response->failed()) {
                Log::error('Bluesky getServiceAuth failed', [
                    'status' => $response->status(),
                    'lxm' => $lxm,
                    'body' => $this->redactResponseBody($response->body()),
                ]);

                return null;
            }

            $token = data_get($response->json(), 'token');

            return is_string($token) && $token !== '' ? $token : null;
        } catch (Throwable $e) {
            Log::error('Bluesky getServiceAuth exception', ['error' => $e->getMessage(), 'lxm' => $lxm]);

            return null;
        }
    }

    /**
     * Resolve the account's real PDS service endpoint from its DID document.
     * Bluesky entryway accounts store the entryway URL as `service`, but video
     * service-auth must be scoped to the actual PDS host (e.g. *.host.bsky.network).
     * Falls back to the stored service URL when the DID document is unavailable.
     */
    private function resolvePdsEndpoint(SocialAccount $account, string $service): string
    {
        $did = (string) $account->platform_user_id;

        try {
            $docUrl = null;
            if (str_starts_with($did, 'did:plc:')) {
                $directory = (string) config('trypost.platforms.bluesky.plc_directory');
                $docUrl = "{$directory}/".rawurlencode($did);
            } elseif (str_starts_with($did, 'did:web:')) {
                // Per the did:web spec, colon-separated segments map to a host
                // plus optional path; a bare host uses /.well-known/did.json.
                $segments = array_map('rawurldecode', explode(':', substr($did, strlen('did:web:'))));
                $host = array_shift($segments);
                $path = $segments === [] ? '/.well-known/did.json' : '/'.implode('/', $segments).'/did.json';
                $docUrl = "https://{$host}{$path}";
            }

            if ($docUrl !== null) {
                $response = $this->socialHttp()->get($docUrl);

                if ($response->successful()) {
                    $services = data_get($response->json(), 'service', []);
                    foreach ($services as $entry) {
                        $type = data_get($entry, 'type');
                        $id = data_get($entry, 'id');
                        if ($type === 'AtprotoPersonalDataServer' || $id === '#atproto_pds') {
                            $endpoint = data_get($entry, 'serviceEndpoint');
                            if (is_string($endpoint) && $endpoint !== '') {
                                return $endpoint;
                            }
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            Log::warning('Bluesky PDS resolution failed', ['did' => $did, 'error' => $e->getMessage()]);
        }

        return $service;
    }

    private function parseFacets(string $text): array
    {
        $facets = [];

        // Parse URLs
        preg_match_all(
            '/(https?:\/\/[^\s]+)/u',
            $text,
            $urlMatches,
            PREG_OFFSET_CAPTURE
        );

        foreach ($urlMatches[0] as $match) {
            $url = $this->trimTrailingUrlPunctuation($match[0]);
            $start = (int) $match[1];
            $end = $start + strlen($url);

            $facets[] = [
                'index' => [
                    'byteStart' => $start,
                    'byteEnd' => $end,
                ],
                'features' => [
                    [
                        '$type' => BlueskyLexicon::FACET_LINK,
                        'uri' => $url,
                    ],
                ],
            ];
        }

        // Parse mentions (@handle.bsky.social)
        preg_match_all(
            '/@([a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?/u',
            $text,
            $mentionMatches,
            PREG_OFFSET_CAPTURE
        );

        $didCache = [];
        foreach ($mentionMatches[0] as $match) {
            $mention = $match[0];
            $handle = substr($mention, 1); // Remove @

            // A mention facet needs the target's DID, not the handle; skip it if unresolvable.
            // Cache by key (not ??) so an unresolvable handle is resolved once, not per occurrence.
            if (! array_key_exists($handle, $didCache)) {
                $didCache[$handle] = $this->resolveHandleToDid($handle);
            }
            $did = $didCache[$handle];
            if ($did === null) {
                continue;
            }

            $start = (int) $match[1];
            $end = $start + strlen($mention);

            $facets[] = [
                'index' => [
                    'byteStart' => $start,
                    'byteEnd' => $end,
                ],
                'features' => [
                    [
                        '$type' => BlueskyLexicon::FACET_MENTION,
                        'did' => $did,
                    ],
                ],
            ];
        }

        // Parse hashtags (#tag)
        preg_match_all(
            '/#[^\s\p{P}]+/u',
            $text,
            $hashtagMatches,
            PREG_OFFSET_CAPTURE
        );

        foreach ($hashtagMatches[0] as $match) {
            $hashtag = $match[0];
            $tag = substr($hashtag, 1); // Remove #
            $start = (int) $match[1];
            $end = $start + strlen($hashtag);

            $facets[] = [
                'index' => [
                    'byteStart' => $start,
                    'byteEnd' => $end,
                ],
                'features' => [
                    [
                        '$type' => BlueskyLexicon::FACET_TAG,
                        'tag' => $tag,
                    ],
                ],
            ];
        }

        return $facets;
    }

    /**
     * Resolve a Bluesky handle to its DID via com.atproto.identity.resolveHandle.
     *
     * resolveHandle is a public read served by the AppView (no auth). Returns
     * null on any failure so the caller can skip the mention facet and publish
     * the @handle as plain text instead of an invalid record.
     */
    private function resolveHandleToDid(string $handle): ?string
    {
        $appView = (string) config('trypost.platforms.bluesky.public_appview');

        try {
            $response = $this->socialHttp()->get(
                "{$appView}/xrpc/".BlueskyLexicon::RESOLVE_HANDLE,
                ['handle' => $handle],
            );

            $did = $response->successful() ? data_get($response->json(), 'did') : null;

            return is_string($did) && str_starts_with($did, 'did:') ? $did : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Trailing sentence punctuation and an unmatched closing paren are almost
     * never part of a URL (e.g. "see https://x.com)."). Mirrors the official
     * atproto link tokenizer so the link facet doesn't over-extend past the URL.
     */
    private function trimTrailingUrlPunctuation(string $url): string
    {
        if (preg_match('/[.,;:!?]$/', $url)) {
            $url = substr($url, 0, -1);
        }

        if (str_ends_with($url, ')') && ! str_contains($url, '(')) {
            $url = substr($url, 0, -1);
        }

        return $url;
    }

    private function buildPostUrl(string $handle, string $postId): string
    {
        $webApp = (string) config('trypost.platforms.bluesky.web_app');

        return "{$webApp}/profile/{$handle}/post/{$postId}";
    }

    private function handleApiError(Response $response): never
    {
        throw BlueskyPublishException::fromApiResponse($response);
    }
}
