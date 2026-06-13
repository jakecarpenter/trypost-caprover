<?php

declare(strict_types=1);

namespace App\Services\Automation;

use App\Enums\PostPlatform\ContentType;

/**
 * Backend mirror of the Generate node's frontend compliance: validates that the
 * number of AI images intended for each selected account fits that account's
 * content-type media rules. Uses the ContentType enum as the single source of
 * truth (same `maxMediaCount`/`supportsImage`/`requiresMedia` the publish flow
 * uses) and reuses the post editor's compliance messages so the wording matches
 * exactly. AI image generation is capped at MAX_GENERATED_IMAGES regardless of
 * how many a platform technically allows.
 */
final class GenerateNodeValidator
{
    public const MAX_GENERATED_IMAGES = 10;

    /**
     * First compliance issue for a generate node's config, or null when valid.
     *
     * @param  array<string, mixed>  $config
     */
    public function issueFor(array $config): ?string
    {
        $accounts = data_get($config, 'accounts');

        if (! is_array($accounts) || $accounts === []) {
            return null;
        }

        // Single source of truth: 0 = text-only, 1 = single image, 2+ = carousel.
        $imageCount = (int) data_get($config, 'target_slide_count', 1);

        foreach ($accounts as $entry) {
            $contentType = ContentType::tryFrom((string) data_get($entry, 'content_type'));

            if (! $contentType instanceof ContentType) {
                continue;
            }

            $issue = $this->issueForAccount($contentType, $imageCount);

            if ($issue !== null) {
                return $issue;
            }
        }

        return null;
    }

    private function issueForAccount(ContentType $contentType, int $imageCount): ?string
    {
        if ($contentType->requiresMedia() && $imageCount === 0) {
            return __('posts.edit.compliance.requires_media');
        }

        if ($imageCount > 0 && ! $contentType->supportsImage()) {
            return __('posts.edit.compliance.no_images');
        }

        $max = min(self::MAX_GENERATED_IMAGES, $contentType->maxMediaCount());

        if ($imageCount > $max) {
            return __('posts.edit.compliance.too_many_files', ['max' => (string) $max]);
        }

        return null;
    }
}
