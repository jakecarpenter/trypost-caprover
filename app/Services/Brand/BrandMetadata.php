<?php

declare(strict_types=1);

namespace App\Services\Brand;

final readonly class BrandMetadata
{
    /**
     * @param  array<int, string>  $voiceTraits
     */
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?string $language = null,
        public ?string $logoUrl = null,
        public ?string $brandColor = null,
        public ?string $backgroundColor = null,
        public ?string $textColor = null,
        public array $voiceTraits = [],
    ) {}

    public function mergeLlm(LlmBrandAnalysis $llm): self
    {
        return new self(
            name: $llm->name ?: $this->name,
            description: $llm->description ?: $this->description,
            language: $llm->language ?: $this->language,
            logoUrl: $this->logoUrl,
            // Prefer deterministically-extracted colors (theme-color meta, CSS
            // custom properties, body rules) — the LLM only sees stripped
            // markdown so its color answers are unreliable.
            brandColor: $this->brandColor ?: ($llm->brandColor ?: null),
            backgroundColor: $this->backgroundColor ?: ($llm->backgroundColor ?: null),
            textColor: $this->textColor ?: ($llm->textColor ?: null),
            voiceTraits: $llm->voiceTraits ?: $this->voiceTraits,
        );
    }

    public function withLogoUrl(?string $logoUrl): self
    {
        return new self(
            name: $this->name,
            description: $this->description,
            language: $this->language,
            logoUrl: $logoUrl,
            brandColor: $this->brandColor,
            backgroundColor: $this->backgroundColor,
            textColor: $this->textColor,
            voiceTraits: $this->voiceTraits,
        );
    }

    public function withBrandColor(?string $brandColor): self
    {
        return new self(
            name: $this->name,
            description: $this->description,
            language: $this->language,
            logoUrl: $this->logoUrl,
            brandColor: $brandColor,
            backgroundColor: $this->backgroundColor,
            textColor: $this->textColor,
            voiceTraits: $this->voiceTraits,
        );
    }

    /**
     * Shape returned to the workspace / brand forms after autofill.
     *
     * Site extraction keeps the literal page background and body text colours.
     * For AI image generation we swap them: page text (usually dark) becomes
     * image background, and page background (usually light) becomes in-image text.
     *
     * @return array{name: ?string, brand_description: ?string, content_language: ?string, brand_color: ?string, background_color: ?string, text_color: ?string, logo_url: ?string, brand_voice_traits: array<int, string>}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'brand_description' => $this->description,
            'content_language' => $this->language,
            'brand_color' => $this->brandColor,
            'background_color' => $this->textColor,
            'text_color' => $this->backgroundColor,
            'logo_url' => $this->logoUrl,
            'brand_voice_traits' => $this->voiceTraits,
        ];
    }
}
