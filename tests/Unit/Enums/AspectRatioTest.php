<?php

declare(strict_types=1);

use App\Enums\PostPlatform\AspectRatio;

test('aspect ratio has the expected cases and values', function () {
    expect(array_column(AspectRatio::cases(), 'value'))
        ->toEqualCanonicalizing(['1:1', '4:5', '16:9', 'original']);
});

test('aspect ratio maps to the correct crop float', function (string $value, float $expected) {
    expect(AspectRatio::from($value)->toFloat())->toBe($expected);
})->with([
    '1:1' => ['1:1', 1.0],
    '4:5' => ['4:5', 4 / 5],
    '16:9' => ['16:9', 16 / 9],
    'original' => ['original', 1.0],
]);
