<?php

declare(strict_types=1);

namespace App\Enums\PostPlatform;

enum AspectRatio: string
{
    case Square = '1:1';
    case Portrait = '4:5';
    case Landscape = '16:9';
    case Original = 'original';

    /**
     * Width-to-height ratio used to center-crop an image. `Original` means "no
     * crop", so it resolves to a square (1.0) only as a safe fallback — callers
     * should bypass cropping entirely for `Original`.
     */
    public function toFloat(): float
    {
        return match ($this) {
            self::Portrait => 4 / 5,
            self::Landscape => 16 / 9,
            self::Square, self::Original => 1.0,
        };
    }
}
