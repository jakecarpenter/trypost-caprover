<?php

declare(strict_types=1);

namespace App\Services\Brand;

use App\Enums\Workspace\BrandVoiceTrait;
use ArrayAccess;

final readonly class LlmBrandAnalysis
{
    /**
     * @param  array<int, string>  $voiceTraits
     */
    public function __construct(
        public string $name = '',
        public string $description = '',
        public string $language = '',
        public string $brandColor = '',
        public string $backgroundColor = '',
        public string $textColor = '',
        public array $voiceTraits = [],
    ) {}

    public static function fromResponse(ArrayAccess|array $response): self
    {
        $traits = data_get($response, 'voice_traits', []);

        return new self(
            name: trim((string) data_get($response, 'name', '')),
            description: trim((string) data_get($response, 'description', '')),
            language: trim((string) data_get($response, 'language', '')),
            brandColor: self::normalizeHex((string) data_get($response, 'brand_color', '')),
            backgroundColor: self::normalizeHex((string) data_get($response, 'background_color', '')),
            textColor: self::normalizeHex((string) data_get($response, 'text_color', '')),
            voiceTraits: BrandVoiceTrait::coerce(is_array($traits) ? $traits : []),
        );
    }

    private static function normalizeHex(string $value): string
    {
        $trimmed = strtolower(trim($value));

        if ($trimmed === '') {
            return '';
        }

        if (! str_starts_with($trimmed, '#')) {
            $trimmed = '#'.$trimmed;
        }

        return preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/', $trimmed) === 1
            ? $trimmed
            : '';
    }
}
