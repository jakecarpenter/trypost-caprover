<?php

declare(strict_types=1);

use App\Ai\Templates\AiTemplateRegistry;
use App\Ai\Templates\ImageCardTemplate;
use App\Ai\Templates\TweetCardImageTemplate;

test('registry resolves keys and defaults to image_card', function () {
    $registry = app(AiTemplateRegistry::class);
    expect($registry->keys())->toBe(['image_card', 'tweet_card', 'tweet_card_image'])
        ->and($registry->find('image_card'))->toBeInstanceOf(ImageCardTemplate::class)
        ->and($registry->find('tweet_card_image'))->toBeInstanceOf(TweetCardImageTemplate::class)
        ->and($registry->default()->key())->toBe('image_card');
});

test('registry throws on unknown key', function () {
    expect(fn () => app(AiTemplateRegistry::class)->find('nope'))->toThrow(InvalidArgumentException::class);
});

test('registry does not contain carousel as a standalone style', function () {
    $registry = app(AiTemplateRegistry::class);
    expect($registry->keys())->not->toContain('carousel');
});
