<?php

declare(strict_types=1);

use App\Enums\Workspace\BrandVoiceTrait;

test('the voice prompt partial renders an instruction for every trait', function () {
    $rendered = view('prompts.post_content._voice', [
        'brand_voice_traits' => BrandVoiceTrait::values(),
    ])->render();

    $lines = array_filter(
        explode("\n", trim($rendered)),
        fn (string $line) => str_starts_with(trim($line), '- '),
    );

    // Every trait must map to exactly one instruction line — if a trait is added
    // to the enum but not to the Blade phrase map, this catches the silent skip.
    expect($lines)->toHaveCount(count(BrandVoiceTrait::values()));
});

test('the voice prompt partial skips unknown traits without erroring', function () {
    $rendered = view('prompts.post_content._voice', [
        'brand_voice_traits' => ['not_a_trait', 'direct'],
    ])->render();

    expect(trim($rendered))->toBe('- Use direct, plain, accessible language.');
});
