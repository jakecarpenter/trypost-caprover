<?php

declare(strict_types=1);

namespace App\Actions\Automation\Node;

use App\Actions\Post\CreatePost;
use App\Ai\Agents\PostContentGenerator;
use App\Ai\Agents\PostContentHumanizer;
use App\DataTransferObjects\Automation\NodeRunResult;
use App\Enums\PostPlatform\ContentType;
use App\Models\AutomationRun;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ai\RecordAiUsage;
use App\Services\Automation\ExpressionResolver;
use App\Services\Automation\GenerateNodeValidator;
use App\Services\Image\PostImagePipeline;
use Illuminate\Support\Facades\Log;

class RunGenerateNode
{
    public function __construct(
        private ExpressionResolver $resolver,
    ) {}

    public function __invoke(AutomationRun $run, array $config): NodeRunResult
    {
        $context = $run->resolverContext();
        $prompt = $this->resolver->resolve(data_get($config, 'prompt_template', ''), $context);

        $accountsConfig = $this->resolveAccountsConfig($config);
        ['format' => $format, 'slide_count' => $slideCount] = $this->deriveFormat($accountsConfig, $config);

        $accountIds = array_values(array_filter(array_map(
            fn ($a) => data_get($a, 'social_account_id'),
            $accountsConfig,
        )));

        $workspace = $run->automation->workspace;

        $activeAccounts = SocialAccount::query()
            ->whereIn('id', $accountIds)
            ->where('workspace_id', $workspace->id)
            ->active()
            ->get()
            ->keyBy('id');

        if ($accountIds !== [] && $activeAccounts->isEmpty()) {
            Log::warning('RunGenerateNode: no active social accounts found, skipping account assignment', [
                'automation_id' => $run->automation_id,
                'social_account_ids' => $accountIds,
            ]);
        }

        $agent = new PostContentGenerator(
            workspace: $workspace,
            format: $format,
            slideCount: $slideCount,
        );

        $generatorResponse = $agent->prompt($prompt);

        RecordAiUsage::recordText(
            workspace: $workspace,
            promptTokens: $generatorResponse->usage->promptTokens,
            completionTokens: $generatorResponse->usage->completionTokens,
            provider: (string) config('ai.default'),
            model: (string) config('ai.default_text_model'),
            metadata: ['agent' => 'post_generator', 'format' => $format, 'source' => 'automation'],
        );

        $structured = $generatorResponse->structured ?? [];

        $structured = $this->humanize($workspace, $structured, $format);

        $content = $format === 'carousel'
            ? (string) data_get($structured, 'caption', '')
            : (string) data_get($structured, 'content', '');

        $user = $this->resolveUser($run);

        $platforms = [];
        foreach ($accountsConfig as $entry) {
            $accountId = data_get($entry, 'social_account_id');
            if (! $accountId || ! $activeAccounts->has($accountId)) {
                if ($accountId) {
                    Log::warning('RunGenerateNode: account no longer active, skipping', [
                        'automation_id' => $run->automation_id,
                        'social_account_id' => $accountId,
                    ]);
                }

                continue;
            }

            $platforms[] = [
                'social_account_id' => $accountId,
                'content_type' => data_get($entry, 'content_type'),
                'meta' => data_get($entry, 'meta', []),
            ];
        }

        $includeImage = (bool) data_get($config, 'include_image', true);

        $brandAccount = $platforms !== []
            ? $activeAccounts->get(data_get($platforms[0], 'social_account_id'))
            : null;

        $contentType = $platforms !== []
            ? ContentType::tryFrom((string) data_get($platforms[0], 'content_type'))
            : null;

        $imageCount = $this->intendedImageCount($format, $slideCount, $includeImage, $structured, $brandAccount);

        // Dry runs do the AI work (so the user sees a real generation) but
        // never persist a Post. Downstream nodes (Publish) read `is_dry_run`
        // and skip their persistence too.
        if ($run->is_dry_run) {
            return NodeRunResult::completed(output: [
                'generated' => [
                    'post_id' => null,
                    'content' => $content,
                    'dry_run' => true,
                    'image_count' => $imageCount,
                ],
            ]);
        }

        $media = [];

        if ($brandAccount) {
            if ($format === 'carousel') {
                $media = app(PostImagePipeline::class)->forCarousel($workspace, $brandAccount, $structured, $contentType);
            } elseif ($includeImage) {
                $media = app(PostImagePipeline::class)->forSingle($workspace, $brandAccount, $structured, $contentType);
            }
        }

        $post = CreatePost::execute($workspace, $user, [
            'content' => $content,
            'media' => $media,
            'platforms' => $platforms,
        ]);

        $run->update(['generated_post_id' => $post->id]);

        return NodeRunResult::completed(output: [
            'generated' => [
                'post_id' => $post->id,
                'content' => $content,
                'post_url' => route('app.posts.show', $post->id),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $structured
     * @return array<string, mixed>
     */
    private function humanize(Workspace $workspace, array $structured, string $format): array
    {
        try {
            $input = $format === 'carousel'
                ? [
                    'caption' => data_get($structured, 'caption', ''),
                    'slides' => array_map(
                        fn ($s) => [
                            'title' => data_get($s, 'title', ''),
                            'body' => data_get($s, 'body', ''),
                        ],
                        data_get($structured, 'slides', []),
                    ),
                ]
                : [
                    'content' => data_get($structured, 'content', ''),
                    'image_title' => data_get($structured, 'image_title', ''),
                    'image_body' => data_get($structured, 'image_body', ''),
                ];

            $humanizer = new PostContentHumanizer($workspace, $format);
            $response = $humanizer->prompt(json_encode($input, JSON_UNESCAPED_UNICODE));
            $humanized = $response->structured ?? [];

            RecordAiUsage::recordText(
                workspace: $workspace,
                promptTokens: $response->usage->promptTokens,
                completionTokens: $response->usage->completionTokens,
                provider: (string) config('ai.default'),
                model: (string) config('ai.default_text_model'),
                metadata: ['agent' => 'post_humanizer', 'format' => $format, 'source' => 'automation'],
            );

            if ($format === 'carousel') {
                $structured['caption'] = data_get($humanized, 'caption', $structured['caption'] ?? '');
                $originalSlides = $structured['slides'] ?? [];
                $humanizedSlides = data_get($humanized, 'slides', []);

                foreach ($originalSlides as $i => $slide) {
                    if (isset($humanizedSlides[$i])) {
                        $originalSlides[$i]['title'] = data_get($humanizedSlides[$i], 'title', $slide['title'] ?? '');
                        $originalSlides[$i]['body'] = data_get($humanizedSlides[$i], 'body', $slide['body'] ?? '');
                    }
                }

                $structured['slides'] = $originalSlides;
            } else {
                $structured['content'] = data_get($humanized, 'content', $structured['content'] ?? '');
                $structured['image_title'] = data_get($humanized, 'image_title', $structured['image_title'] ?? '');
                $structured['image_body'] = data_get($humanized, 'image_body', $structured['image_body'] ?? '');
            }
        } catch (\Throwable $e) {
            Log::warning('RunGenerateNode: PostContentHumanizer failed, using generator output as-is', [
                'error' => $e->getMessage(),
            ]);
        }

        return $structured;
    }

    /**
     * Derive the generator format and slide count from per-account content types.
     *
     * Carousel-capable content types:
     *   - instagram_feed       (Instagram feed carousel = multi-image feed post)
     *   - linkedin_carousel    (LinkedIn personal carousel PDF)
     *   - linkedin_page_carousel (LinkedIn page carousel PDF)
     *   - pinterest_carousel   (Pinterest carousel pin)
     *   - tiktok_photo         (TikTok photo carousel)
     *
     * When at least one account has a carousel-capable content type AND
     * target_slide_count > 1, the generator is told to produce a carousel with
     * that many slides. Otherwise it falls back to a single-post format.
     *
     * @param  array<int, array{social_account_id: string, content_type: ?string, meta: array<string, mixed>}>  $accountsConfig
     * @param  array<string, mixed>  $config
     * @return array{format: string, slide_count: int}
     */
    public function deriveFormat(array $accountsConfig, array $config): array
    {
        // Multi-image capability comes from the same ContentType rules the
        // publish flow uses — never a hardcoded list — so facebook_post,
        // tiktok_photo, linkedin_carousel etc. are all recognised. We cap the
        // slide count at MAX_GENERATED_IMAGES and at the tightest selected
        // account's limit.
        $maxImagesAcross = 0;
        foreach ($accountsConfig as $entry) {
            $contentType = ContentType::tryFrom((string) data_get($entry, 'content_type'));
            if ($contentType instanceof ContentType && $contentType->supportsImage() && $contentType->maxMediaCount() > 1) {
                $maxImagesAcross = max($maxImagesAcross, $contentType->maxMediaCount());
            }
        }

        $targetSlideCount = (int) data_get($config, 'target_slide_count', 1);

        if ($maxImagesAcross > 1 && $targetSlideCount > 1) {
            $cap = min(GenerateNodeValidator::MAX_GENERATED_IMAGES, $maxImagesAcross);

            return ['format' => 'carousel', 'slide_count' => min($targetSlideCount, $cap)];
        }

        return ['format' => 'single', 'slide_count' => 1];
    }

    /**
     * Number of images that would be attached for the resolved format. Used as
     * the dry-run indicator and mirrors the non-dry image generation branches:
     * one per slide for carousels, one for single posts when images are enabled.
     *
     * @param  array<string, mixed>  $structured
     */
    private function intendedImageCount(string $format, int $slideCount, bool $includeImage, array $structured, ?SocialAccount $brandAccount): int
    {
        if (! $brandAccount) {
            return 0;
        }

        if ($format === 'carousel') {
            $slides = data_get($structured, 'slides', []);

            return is_array($slides) ? count($slides) : $slideCount;
        }

        return $includeImage ? 1 : 0;
    }

    private function resolveUser(AutomationRun $run): User
    {
        if ($run->automation->user_id) {
            return $run->automation->user;
        }

        return $run->automation->workspace->owner;
    }

    /**
     * Read the current `accounts` shape and fall back to the legacy
     * `social_account_ids` array so older automations keep running until
     * the user re-opens and saves the node.
     *
     * @param  array<string, mixed>  $config
     * @return array<int, array{social_account_id: string, content_type: ?string, meta: array<string, mixed>}>
     */
    private function resolveAccountsConfig(array $config): array
    {
        $accounts = data_get($config, 'accounts');

        if (is_array($accounts)) {
            return array_values(array_map(fn ($entry) => [
                'social_account_id' => (string) data_get($entry, 'social_account_id', ''),
                'content_type' => data_get($entry, 'content_type'),
                'meta' => (array) data_get($entry, 'meta', []),
            ], $accounts));
        }

        $legacy = data_get($config, 'social_account_ids', []);

        if (! is_array($legacy)) {
            return [];
        }

        return array_values(array_map(fn ($id) => [
            'social_account_id' => (string) $id,
            'content_type' => null,
            'meta' => [],
        ], $legacy));
    }
}
