<?php

declare(strict_types=1);

use App\Ai\Templates\TemplateContext;
use App\Ai\Templates\TweetCardImageTemplate;
use App\Models\Workspace;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;

test('tweet card image template identity', function () {
    $t = new TweetCardImageTemplate;
    $workspace = Workspace::factory()->create();
    $singleContext = new TemplateContext($workspace, null, 'x_post', 1, false);
    $carouselContext = new TemplateContext($workspace, null, 'instagram_carousel', 2, true);

    expect($t->key())->toBe('tweet_card_image')
        ->and($t->needsAccount())->toBeTrue()
        ->and($t->generatorFormat())->toBe('tweet_card')
        ->and($t->promptView($singleContext))->toBe('prompts.post_content.tweet_card_image')
        ->and($t->promptView($carouselContext))->toBe('prompts.post_content.tweet_card_image_carousel');
});

test('tweet card image single schema returns tweet_text and image_keywords', function () {
    $t = new TweetCardImageTemplate;
    $workspace = Workspace::factory()->create();
    $context = new TemplateContext($workspace, null, 'x_post', 1, false);

    $schema = new JsonSchemaTypeFactory;
    $result = $t->schema($schema, $context);

    expect($result)->toHaveKey('tweet_text')
        ->and($result)->toHaveKey('image_keywords')
        ->and($result)->not->toHaveKey('caption');
});

test('tweet card image carousel schema returns caption and slides with image_keywords', function () {
    $t = new TweetCardImageTemplate;
    $workspace = Workspace::factory()->create();
    $context = new TemplateContext($workspace, null, 'instagram_carousel', 3, true);

    $schema = new JsonSchemaTypeFactory;
    $result = $t->schema($schema, $context);

    expect($result)->toHaveKeys(['caption', 'slides']);
});
