<?php

declare(strict_types=1);

namespace App\Ai\Agents\Concerns;

use App\Enums\PostPlatform\ContentType;

trait ResolvesPlatformCopyBudget
{
    /**
     * Resolve the per-platform copy budget for a content-type string so every
     * agent that writes OR rewrites post text honours the same character caps.
     * Returns nulls when the platform is unknown, leaving the prompt
     * platform-agnostic.
     *
     * @return array{hard_max_chars: ?int, target_chars: ?int, platform_label: ?string}
     */
    protected function platformCopyBudget(?string $platformContext): array
    {
        $contentType = $platformContext !== null ? ContentType::tryFrom($platformContext) : null;

        if (! $contentType instanceof ContentType) {
            return ['hard_max_chars' => null, 'target_chars' => null, 'platform_label' => null];
        }

        $platform = $contentType->platform();

        return [
            'hard_max_chars' => $platform->maxContentLength(),
            'target_chars' => $platform->recommendedAiContentLength(),
            'platform_label' => $platform->label(),
        ];
    }
}
