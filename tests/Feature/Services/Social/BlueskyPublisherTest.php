<?php

declare(strict_types=1);

use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Exceptions\TokenExpiredException;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Media\MediaOptimizer;
use App\Services\Social\BlueskyPublisher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);

    $this->socialAccount = SocialAccount::factory()->bluesky()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => 'did:plc:testuser123',
        'username' => 'testuser.bsky.social',
    ]);

    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'content' => 'Hello from Bluesky!',
    ]);

    $this->postPlatform = PostPlatform::factory()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->socialAccount->id,
        'platform' => Platform::Bluesky,
        'content_type' => ContentType::BlueskyPost,
    ]);

    $this->publisher = new BlueskyPublisher;
});

test('bluesky publisher can publish text-only post', function () {
    Http::fake([
        'https://bsky.social/xrpc/com.atproto.repo.createRecord' => Http::response([
            'uri' => 'at://did:plc:testuser123/app.bsky.feed.post/3abc123xyz',
            'cid' => 'bafyreiabc123',
        ], 200),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result)->toHaveKey('id');
    expect($result)->toHaveKey('url');
    expect($result['id'])->toBe('3abc123xyz');
    expect($result['url'])->toContain('bsky.app/profile/testuser.bsky.social/post/3abc123xyz');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'createRecord')
            && $request['record']['text'] === 'Hello from Bluesky!';
    });
});

test('bluesky publisher parses URLs as facets', function () {
    $this->post->update(['content' => 'Check out https://example.com for more info!']);

    Http::fake([
        'https://bsky.social/xrpc/com.atproto.repo.createRecord' => Http::response([
            'uri' => 'at://did:plc:testuser123/app.bsky.feed.post/3abc123xyz',
            'cid' => 'bafyreiabc123',
        ], 200),
    ]);

    $this->publisher->publish($this->postPlatform);

    Http::assertSent(function ($request) {
        $record = $request['record'];

        return isset($record['facets'])
            && count($record['facets']) > 0
            && $record['facets'][0]['features'][0]['$type'] === 'app.bsky.richtext.facet#link';
    });
});

test('bluesky publisher parses hashtags as facets', function () {
    $this->post->update(['content' => 'Hello #bluesky #test']);

    Http::fake([
        'https://bsky.social/xrpc/com.atproto.repo.createRecord' => Http::response([
            'uri' => 'at://did:plc:testuser123/app.bsky.feed.post/3abc123xyz',
            'cid' => 'bafyreiabc123',
        ], 200),
    ]);

    $this->publisher->publish($this->postPlatform);

    Http::assertSent(function ($request) {
        $record = $request['record'];

        return isset($record['facets']) && count($record['facets']) >= 2;
    });
});

test('bluesky publisher strips trailing punctuation from URL facets', function () {
    $this->post->update(['content' => 'see https://example.com).']);

    Http::fake([
        config('trypost.platforms.bluesky.default_service').'/xrpc/com.atproto.repo.createRecord' => Http::response([
            'uri' => 'at://did:plc:testuser123/app.bsky.feed.post/3abc123xyz',
            'cid' => 'bafyreiabc123',
        ], 200),
    ]);

    $this->publisher->publish($this->postPlatform);

    Http::assertSent(function ($request) {
        $link = collect($request['record']['facets'] ?? [])
            ->first(fn ($facet) => $facet['features'][0]['$type'] === 'app.bsky.richtext.facet#link');

        return $link
            && $link['features'][0]['uri'] === 'https://example.com'
            && $link['index']['byteEnd'] === $link['index']['byteStart'] + strlen('https://example.com');
    });
});

test('bluesky publisher keeps a closing paren that has a matching open paren', function () {
    $this->post->update(['content' => 'see https://en.wikipedia.org/wiki/Foo_(bar)']);

    Http::fake([
        config('trypost.platforms.bluesky.default_service').'/xrpc/com.atproto.repo.createRecord' => Http::response([
            'uri' => 'at://did:plc:testuser123/app.bsky.feed.post/3abc123xyz',
            'cid' => 'bafyreiabc123',
        ], 200),
    ]);

    $this->publisher->publish($this->postPlatform);

    Http::assertSent(function ($request) {
        $link = collect($request['record']['facets'] ?? [])
            ->first(fn ($facet) => $facet['features'][0]['$type'] === 'app.bsky.richtext.facet#link');

        // The trailing ')' is part of the URL because it has a matching '('.
        return $link && $link['features'][0]['uri'] === 'https://en.wikipedia.org/wiki/Foo_(bar)';
    });
});

test('bluesky publisher computes byte offsets after multibyte characters', function () {
    $this->post->update(['content' => 'Olá 🎉 #café']);

    Http::fake([
        config('trypost.platforms.bluesky.default_service').'/xrpc/com.atproto.repo.createRecord' => Http::response([
            'uri' => 'at://did:plc:testuser123/app.bsky.feed.post/3abc123xyz',
            'cid' => 'bafyreiabc123',
        ], 200),
    ]);

    $this->publisher->publish($this->postPlatform);

    Http::assertSent(function ($request) {
        $text = $request['record']['text'];
        $tag = collect($request['record']['facets'] ?? [])
            ->first(fn ($facet) => $facet['features'][0]['$type'] === 'app.bsky.richtext.facet#tag');

        // byteStart must be the UTF-8 byte position of '#café', not its character index.
        return $tag
            && $tag['index']['byteStart'] === strpos($text, '#café')
            && $tag['index']['byteEnd'] === strpos($text, '#café') + strlen('#café');
    });
});

test('bluesky publisher resolves mentions to DIDs as facets', function () {
    $this->post->update(['content' => 'Shout out to @friend.bsky.social']);

    Http::fake([
        // Wildcard so the fake matches whichever endpoint resolveHandleToDid()
        // tries first (the account PDS, public AppView, or bsky.social) and the
        // test stays isolated from the configured service URL.
        '*/xrpc/com.atproto.identity.resolveHandle*' => Http::response([
            'did' => 'did:plc:friend456',
        ], 200),
        config('trypost.platforms.bluesky.default_service').'/xrpc/com.atproto.repo.createRecord' => Http::response([
            'uri' => 'at://did:plc:testuser123/app.bsky.feed.post/3abc123xyz',
            'cid' => 'bafyreiabc123',
        ], 200),
    ]);

    $this->publisher->publish($this->postPlatform);

    Http::assertSent(function ($request) {
        $record = $request->data()['record'] ?? null;

        if (! $record || ! isset($record['facets'])) {
            return false;
        }

        foreach ($record['facets'] as $facet) {
            $feature = $facet['features'][0];
            if ($feature['$type'] === 'app.bsky.richtext.facet#mention') {
                // The facet must carry the resolved DID, not the raw handle.
                return $feature['did'] === 'did:plc:friend456';
            }
        }

        return false;
    });
});

test('bluesky publisher skips mention facet when handle cannot be resolved', function () {
    $this->post->update(['content' => 'Shout out to @ghost.bsky.social']);

    Http::fake([
        '*/xrpc/com.atproto.identity.resolveHandle*' => Http::response(['error' => 'InvalidRequest'], 400),
        config('trypost.platforms.bluesky.default_service').'/xrpc/com.atproto.repo.createRecord' => Http::response([
            'uri' => 'at://did:plc:testuser123/app.bsky.feed.post/3abc123xyz',
            'cid' => 'bafyreiabc123',
        ], 200),
    ]);

    // Post still publishes; the unresolved @handle stays as plain text.
    $result = $this->publisher->publish($this->postPlatform);

    expect($result['id'])->toBe('3abc123xyz');

    Http::assertSent(function ($request) {
        $record = $request->data()['record'] ?? null;

        if (! $record) {
            return false;
        }

        $hasMentionFacet = collect($record['facets'] ?? [])->contains(
            fn ($facet) => $facet['features'][0]['$type'] === 'app.bsky.richtext.facet#mention'
        );

        return str_contains($record['text'], '@ghost.bsky.social') && ! $hasMentionFacet;
    });
});

test('bluesky publisher publishes as plain text when handle resolution errors', function () {
    $this->post->update(['content' => 'hi @friend.bsky.social']);

    Http::fake(function ($request) {
        if (str_contains($request->url(), 'resolveHandle')) {
            throw new ConnectionException('connection refused');
        }

        return Http::response([
            'uri' => 'at://did:plc:testuser123/app.bsky.feed.post/3abc123xyz',
            'cid' => 'bafyreiabc123',
        ], 200);
    });

    // A network error resolving the handle must degrade to plain text, not fail the post.
    $result = $this->publisher->publish($this->postPlatform);

    expect($result['id'])->toBe('3abc123xyz');

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'createRecord')) {
            return false;
        }

        $hasMention = collect($request['record']['facets'] ?? [])
            ->contains(fn ($facet) => $facet['features'][0]['$type'] === 'app.bsky.richtext.facet#mention');

        return str_contains($request['record']['text'], '@friend.bsky.social') && ! $hasMention;
    });
});

test('bluesky publisher builds the post url from the configured web app host', function () {
    config(['trypost.platforms.bluesky.web_app' => 'https://custom.bsky.example']);

    Http::fake([
        config('trypost.platforms.bluesky.default_service').'/xrpc/com.atproto.repo.createRecord' => Http::response([
            'uri' => 'at://did:plc:testuser123/app.bsky.feed.post/3abc123xyz',
            'cid' => 'bafyreiabc123',
        ], 200),
    ]);

    $result = $this->publisher->publish($this->postPlatform);

    expect($result['url'])->toBe('https://custom.bsky.example/profile/testuser.bsky.social/post/3abc123xyz');
});

test('bluesky publisher resolves some mentions and skips the unresolvable ones', function () {
    $this->post->update(['content' => 'cc @good.bsky.social and @bad.bsky.social']);

    Http::fake(function ($request) {
        if (str_contains($request->url(), 'resolveHandle')) {
            // `good` resolves; `bad` answers 200 without a DID — treated as
            // unresolvable, exercising the str_starts_with('did:') guard.
            return str_contains($request->url(), 'good.bsky.social')
                ? Http::response(['did' => 'did:plc:good999'], 200)
                : Http::response([], 200);
        }

        return Http::response([
            'uri' => 'at://did:plc:testuser123/app.bsky.feed.post/3abc123xyz',
            'cid' => 'bafyreiabc123',
        ], 200);
    });

    $this->publisher->publish($this->postPlatform);

    Http::assertSent(function ($request) {
        $record = $request->data()['record'] ?? null;

        if (! $record) {
            return false;
        }

        $mentions = collect($record['facets'] ?? [])
            ->filter(fn ($facet) => $facet['features'][0]['$type'] === 'app.bsky.richtext.facet#mention');

        // Only the resolvable handle becomes a facet; the other stays plain text.
        return $mentions->count() === 1
            && $mentions->first()['features'][0]['did'] === 'did:plc:good999'
            && str_contains($record['text'], '@bad.bsky.social');
    });
});

test('bluesky publisher resolves a repeated handle only once', function () {
    $this->post->update(['content' => 'thanks @dup.bsky.social, really @dup.bsky.social']);

    Http::fake([
        '*/xrpc/com.atproto.identity.resolveHandle*' => Http::response(['did' => 'did:plc:dup789'], 200),
        config('trypost.platforms.bluesky.default_service').'/xrpc/com.atproto.repo.createRecord' => Http::response([
            'uri' => 'at://did:plc:testuser123/app.bsky.feed.post/3abc123xyz',
            'cid' => 'bafyreiabc123',
        ], 200),
    ]);

    $this->publisher->publish($this->postPlatform);

    // The same handle appears twice but the per-post cache resolves it once.
    $resolveCalls = Http::recorded(fn ($request) => str_contains($request->url(), 'resolveHandle'))->count();
    expect($resolveCalls)->toBe(1);

    // Both occurrences still become mention facets carrying the DID.
    Http::assertSent(function ($request) {
        $record = $request->data()['record'] ?? null;

        if (! $record) {
            return false;
        }

        $mentions = collect($record['facets'] ?? [])
            ->filter(fn ($facet) => $facet['features'][0]['$type'] === 'app.bsky.richtext.facet#mention');

        return $mentions->count() === 2
            && $mentions->every(fn ($facet) => $facet['features'][0]['did'] === 'did:plc:dup789');
    });
});

test('bluesky publisher attaches an uploaded image as an embed', function () {
    $this->post->update([
        'media' => [
            [
                'id' => 'test-media-id',
                'path' => 'media/2026-01/test-image.jpg',
                'url' => 'https://example.com/media/2026-01/test-image.jpg',
                'mime_type' => 'image/jpeg',
                'original_filename' => 'test.jpg',
            ],
        ],
    ]);

    $this->mock(MediaOptimizer::class)
        ->shouldReceive('optimizeImage')
        ->andReturnUsing(fn () => tap(tempnam(sys_get_temp_dir(), 'bsky_test_'), fn ($f) => file_put_contents($f, str_repeat('x', 1024))));

    Http::fake(function ($request) {
        if (str_contains($request->url(), 'uploadBlob')) {
            return Http::response([
                'blob' => ['$type' => 'blob', 'ref' => ['$link' => 'bafkreiabc123'], 'mimeType' => 'image/jpeg', 'size' => 1024],
            ], 200);
        }

        if (str_contains($request->url(), 'createRecord')) {
            return Http::response([
                'uri' => 'at://did:plc:testuser123/app.bsky.feed.post/3abc123xyz',
                'cid' => 'bafyreiabc123',
            ], 200);
        }

        return Http::response(str_repeat('x', 1024), 200); // media download
    });

    $this->publisher->publish($this->postPlatform);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'createRecord')) {
            return false;
        }

        $embed = $request['record']['embed'] ?? null;

        return $embed
            && $embed['$type'] === 'app.bsky.embed.images'
            && count($embed['images']) === 1
            && data_get($embed, 'images.0.image.ref.$link') === 'bafkreiabc123';
    });
});

test('bluesky publisher refreshes token when expired', function () {
    $this->socialAccount->update(['token_expires_at' => now()->subHour()]);

    Http::fake([
        'https://bsky.social/xrpc/com.atproto.server.refreshSession' => Http::response([
            'did' => 'did:plc:testuser123',
            'handle' => 'testuser.bsky.social',
            'accessJwt' => 'new-access-token',
            'refreshJwt' => 'new-refresh-token',
        ], 200),
        'https://bsky.social/xrpc/com.atproto.repo.createRecord' => Http::response([
            'uri' => 'at://did:plc:testuser123/app.bsky.feed.post/3abc123xyz',
            'cid' => 'bafyreiabc123',
        ], 200),
    ]);

    $this->publisher->publish($this->postPlatform);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'refreshSession');
    });

    $this->socialAccount->refresh();
    expect($this->socialAccount->access_token)->toBe('new-access-token');
});

test('bluesky publisher throws exception on api error', function () {
    Http::fake([
        'https://bsky.social/xrpc/com.atproto.repo.createRecord' => Http::response([
            'error' => 'InvalidRequest',
            'message' => 'Something went wrong',
        ], 400),
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(Exception::class);
});

test('bluesky publisher throws token expired exception on auth error', function () {
    Http::fake([
        'https://bsky.social/xrpc/com.atproto.repo.createRecord' => Http::response([
            'error' => 'ExpiredToken',
            'message' => 'Token has expired',
        ], 401),
    ]);

    expect(fn () => $this->publisher->publish($this->postPlatform))
        ->toThrow(TokenExpiredException::class);
});

test('bluesky publisher optimizes images before upload', function () {
    $tempFile = tempnam(sys_get_temp_dir(), 'bsky_test_');
    file_put_contents($tempFile, str_repeat('x', 1024));

    $this->post->update([
        'media' => [
            [
                'id' => 'test-media-id',
                'path' => 'media/2026-01/test-image.jpg',
                'url' => 'https://example.com/media/2026-01/test-image.jpg',
                'mime_type' => 'image/jpeg',
                'original_filename' => 'test.jpg',
            ],
        ],
    ]);

    $this->mock(MediaOptimizer::class)
        ->shouldReceive('optimizeImage')
        ->once()
        ->with(Mockery::any(), Platform::Bluesky)
        ->andReturn($tempFile);

    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, 'uploadBlob')) {
            return Http::response([
                'blob' => [
                    '$type' => 'blob',
                    'ref' => ['$link' => 'bafkreiabc123'],
                    'mimeType' => 'image/jpeg',
                    'size' => 1024,
                ],
            ], 200);
        }

        if (str_contains($url, 'createRecord')) {
            return Http::response([
                'uri' => 'at://did:plc:testuser123/app.bsky.feed.post/3abc123xyz',
                'cid' => 'bafyreiabc123',
            ], 200);
        }

        // Media download fallback (covers relative storage URLs in test env)
        return Http::response(str_repeat('x', 1024), 200);
    });

    $this->publisher->publish($this->postPlatform);

    @unlink($tempFile);
});

test('bluesky publisher handles media download failure gracefully', function () {
    $this->post->update([
        'media' => [
            [
                'id' => 'test-media-id',
                'path' => 'media/2026-01/test-image.jpg',
                'url' => 'https://example.com/media/2026-01/test-image.jpg',
                'mime_type' => 'image/jpeg',
                'original_filename' => 'test.jpg',
            ],
        ],
    ]);

    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, 'createRecord')) {
            return Http::response([
                'uri' => 'at://did:plc:testuser123/app.bsky.feed.post/3textonly',
                'cid' => 'bafyreiabc123',
            ], 200);
        }

        // CDN download returns 404 — blob upload is skipped
        return Http::response('Not Found', 404);
    });

    // When media download fails, uploadBlob returns null and post publishes as text-only
    $result = $this->publisher->publish($this->postPlatform);

    expect($result)->toHaveKey('id');
    expect($result['id'])->toBe('3textonly');

    // The createRecord request should NOT contain an embed (no images uploaded)
    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'createRecord')) {
            return false;
        }
        $record = $request['record'];

        return ! isset($record['embed']);
    });
});

test('bluesky publisher limits images to 4', function () {
    $mediaItems = [];
    for ($i = 0; $i < 6; $i++) {
        $mediaItems[] = [
            'id' => "test-media-{$i}",
            'path' => "media/2026-01/test-image-{$i}.jpg",
            'url' => "https://example.com/media/2026-01/test-image-{$i}.jpg",
            'mime_type' => 'image/jpeg',
            'original_filename' => "test-{$i}.jpg",
        ];
    }
    $this->post->update(['media' => $mediaItems]);

    $this->mock(MediaOptimizer::class)
        ->shouldReceive('optimizeImage')
        ->andReturnUsing(fn () => tap(tempnam(sys_get_temp_dir(), 'bsky_test_'), fn ($f) => file_put_contents($f, str_repeat('x', 1024))));

    Http::fake(function ($request) {
        if (str_contains($request->url(), 'uploadBlob')) {
            return Http::response([
                'blob' => ['$type' => 'blob', 'ref' => ['$link' => 'bafkreiabc123'], 'mimeType' => 'image/jpeg', 'size' => 1024],
            ], 200);
        }

        if (str_contains($request->url(), 'createRecord')) {
            return Http::response([
                'uri' => 'at://did:plc:testuser123/app.bsky.feed.post/3abc123xyz',
                'cid' => 'bafyreiabc123',
            ], 200);
        }

        return Http::response(str_repeat('x', 1024), 200); // media download
    });

    $this->publisher->publish($this->postPlatform);

    // Six images provided, but Bluesky caps at 4: only 4 blobs upload and the embed carries 4.
    $uploads = Http::recorded(fn ($request) => str_contains($request->url(), 'uploadBlob'))->count();
    expect($uploads)->toBe(4);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'createRecord')
            && count($request['record']['embed']['images'] ?? []) === 4;
    });
});
