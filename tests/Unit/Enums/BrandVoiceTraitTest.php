<?php

declare(strict_types=1);

use App\Enums\Workspace\BrandVoiceTrait;

test('coerce drops invalid and non-string values', function () {
    expect(BrandVoiceTrait::coerce(['third_person', 'not_a_trait', 42, null, 'direct']))
        ->toBe(['third_person', 'direct']);
});

test('coerce keeps only the first value of each single-select dimension', function () {
    // Two POV + two formality values — only the first of each survives.
    expect(BrandVoiceTrait::coerce(['third_person', 'first_person', 'formal', 'casual']))
        ->toBe(['third_person', 'formal']);
});

test('coerce stacks multiple style traits', function () {
    expect(BrandVoiceTrait::coerce(['direct', 'concise', 'no_hype']))
        ->toBe(['direct', 'concise', 'no_hype']);
});

test('coerce preserves input order', function () {
    expect(BrandVoiceTrait::coerce(['no_hype', 'third_person', 'direct']))
        ->toBe(['no_hype', 'third_person', 'direct']);
});

test('coerce returns empty for empty input', function () {
    expect(BrandVoiceTrait::coerce([]))->toBe([]);
});

test('every trait maps to a group, and style is the only multi-select group', function () {
    $single = BrandVoiceTrait::singleSelectGroups();

    foreach (BrandVoiceTrait::cases() as $trait) {
        $group = $trait->group();
        expect($group)->not->toBeEmpty();

        if ($group !== 'style') {
            expect($single)->toContain($group);
        }
    }
});

test('grouped buckets every trait value exactly once', function () {
    $flat = array_merge(...array_values(BrandVoiceTrait::grouped()));

    expect($flat)->toEqualCanonicalizing(BrandVoiceTrait::values());
});
