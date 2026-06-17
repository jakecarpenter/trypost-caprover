<?php

declare(strict_types=1);

namespace App\Services\Image;

use App\Enums\Media\Source;
use App\Enums\Media\Type as MediaType;
use App\Enums\PostPlatform\ContentType;
use App\Models\SocialAccount;
use App\Models\Workspace;
use Illuminate\Support\Facades\Storage;

class PostImagePipeline
{
    public function __construct(
        private TemplateImageGenerator $generator,
    ) {}

    /**
     * Render the single AI image for a structured generator output. Returns a
     * one-element media-item array when an image is produced, or an empty array
     * when the generator renders nothing.
     *
     * @param  array<string, mixed>  $structured
     * @return array<int, array<string, mixed>>
     */
    public function forSingle(Workspace $workspace, SocialAccount $account, array $structured, ?ContentType $contentType, bool $applyBrandVisuals = true): array
    {
        ['width' => $width, 'height' => $height] = $this->dimensionsForContentType($contentType);

        $rendered = $this->generator->render(
            workspace: $workspace,
            socialAccount: $account,
            title: (string) data_get($structured, 'image_title', ''),
            body: (string) data_get($structured, 'image_body', ''),
            imageKeywords: data_get($structured, 'image_keywords', []),
            width: $width,
            height: $height,
            applyBrandVisuals: $applyBrandVisuals,
        );

        if (! $rendered) {
            return [];
        }

        return [$this->buildAiMediaItem($workspace, $rendered)];
    }

    /**
     * Render one AI image per slide in the structured carousel output. Slides
     * that render nothing are skipped.
     *
     * @param  array<string, mixed>  $structured
     * @return array<int, array<string, mixed>>
     */
    public function forCarousel(Workspace $workspace, SocialAccount $account, array $structured, ?ContentType $contentType, bool $applyBrandVisuals = true): array
    {
        ['width' => $width, 'height' => $height] = $this->dimensionsForContentType($contentType);

        $media = [];

        foreach (data_get($structured, 'slides', []) as $slide) {
            $rendered = $this->generator->render(
                workspace: $workspace,
                socialAccount: $account,
                title: (string) data_get($slide, 'title', ''),
                body: (string) data_get($slide, 'body', ''),
                imageKeywords: data_get($slide, 'image_keywords', []),
                width: $width,
                height: $height,
                applyBrandVisuals: $applyBrandVisuals,
            );

            if ($rendered) {
                $media[] = $this->buildAiMediaItem($workspace, $rendered);
            }
        }

        return $media;
    }

    /**
     * Render a tweet-card image for the given text and return a one-element
     * media-item array, or an empty array when the generator renders nothing.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forTweetCard(Workspace $workspace, SocialAccount $account, string $tweetText): array
    {
        $rendered = $this->generator->renderTweetCard($workspace, $account, $tweetText);

        if (! $rendered) {
            return [];
        }

        return [$this->buildAiMediaItem($workspace, $rendered)];
    }

    /**
     * Resolve the AI image dimensions for the given content type, falling back
     * to the generator defaults (4:5 portrait) when no content type is known.
     *
     * @return array{width: int, height: int}
     */
    private function dimensionsForContentType(?ContentType $contentType): array
    {
        return $contentType
            ? $contentType->aiImageDimensions()
            : ['width' => TemplateImageGenerator::DEFAULT_WIDTH, 'height' => TemplateImageGenerator::DEFAULT_HEIGHT];
    }

    /**
     * @param  array{path: string, source_meta: array<string, mixed>}  $rendered
     * @return array<string, mixed>
     */
    private function buildAiMediaItem(Workspace $workspace, array $rendered): array
    {
        $media = $workspace->media()->create([
            'collection' => 'ai-generated',
            'type' => MediaType::Image,
            'path' => $rendered['path'],
            'original_filename' => basename($rendered['path']),
            'mime_type' => 'image/webp',
            'size' => Storage::size($rendered['path']),
            'order' => 0,
        ]);

        return [
            'id' => $media->id,
            'path' => $media->path,
            'url' => $media->url,
            'type' => 'image',
            'mime_type' => 'image/webp',
            'source' => Source::Ai->value,
            'source_meta' => $rendered['source_meta'],
        ];
    }
}
