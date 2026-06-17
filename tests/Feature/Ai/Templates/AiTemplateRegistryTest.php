<?php

declare(strict_types=1);

use App\Ai\Templates\AiTemplateRegistry;
use App\Ai\Templates\ImageCardTemplate;

test('registry resolves keys and defaults to image_card', function () {
    $registry = app(AiTemplateRegistry::class);
    expect($registry->keys())->toContain('image_card', 'carousel', 'tweet_card')
        ->and($registry->find('image_card'))->toBeInstanceOf(ImageCardTemplate::class)
        ->and($registry->default()->key())->toBe('image_card');
});

test('registry throws on unknown key', function () {
    expect(fn () => app(AiTemplateRegistry::class)->find('nope'))->toThrow(InvalidArgumentException::class);
});
