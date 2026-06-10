<?php

declare(strict_types=1);

use App\Actions\Automation\Node\RunGenerateNode;
use App\Ai\Agents\PostContentGenerator;
use App\Ai\Agents\PostContentHumanizer;
use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\ContentType;
use App\Models\AutomationRun;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\Workspace;

it('creates a draft post and writes generated output to run context', function () {
    PostContentGenerator::fake([
        ['content' => 'Great post about Stripe Radar', 'image_title' => 'Stripe Radar', 'image_body' => 'Fraud prevention', 'image_keywords' => ['stripe', 'fraud']],
    ]);

    PostContentHumanizer::fake([
        ['content' => 'Great post about Stripe Radar (humanized)', 'image_title' => 'Stripe Radar', 'image_body' => 'Fraud prevention'],
    ]);

    $run = AutomationRun::factory()->create([
        'context' => ['trigger' => ['title' => 'Stripe Radar update']],
    ]);

    $result = app(RunGenerateNode::class)($run, [
        'accounts' => [],
        'prompt_template' => 'Generate a post about: {{ trigger.title }}',
        'image_source' => 'none',
    ]);

    expect($result->status->value)->toBe('completed');
    expect($result->output)->toHaveKey('generated');
    expect($result->output['generated'])->toHaveKey('post_id');
    expect($result->output['generated']['content'])->toBe('Great post about Stripe Radar (humanized)');

    $run->refresh();
    expect($run->generated_post_id)->not->toBeNull();

    $post = Post::find($run->generated_post_id);
    expect($post)->not->toBeNull();
    expect($post->status)->toBe(PostStatus::Draft);
});

it('interpolates the prompt template before passing it to the generator', function () {
    PostContentGenerator::fake([
        ['content' => 'Interpolated content', 'image_title' => 'Title', 'image_body' => 'Body', 'image_keywords' => ['kw']],
    ]);

    PostContentHumanizer::fake([
        ['content' => 'Interpolated content humanized', 'image_title' => 'Title', 'image_body' => 'Body'],
    ]);

    $run = AutomationRun::factory()->create([
        'context' => ['trigger' => ['topic' => 'AI Agents']],
    ]);

    app(RunGenerateNode::class)($run, [
        'accounts' => [],
        'prompt_template' => 'Write about {{ trigger.topic }}',
    ]);

    PostContentGenerator::assertPrompted(fn ($prompt) => $prompt->contains('Write about AI Agents'));
});

it('still creates a draft post when humanizer fails', function () {
    PostContentGenerator::fake([
        ['content' => 'Raw content', 'image_title' => 'Title', 'image_body' => 'Body', 'image_keywords' => ['kw']],
    ]);

    PostContentHumanizer::fake(fn () => throw new RuntimeException('Humanizer down'));

    $run = AutomationRun::factory()->create([
        'context' => ['trigger' => ['title' => 'test']],
    ]);

    $result = app(RunGenerateNode::class)($run, [
        'accounts' => [],
        'prompt_template' => 'Write about {{ trigger.title }}',
    ]);

    expect($result->status->value)->toBe('completed');
    expect($result->output['generated']['content'])->toBe('Raw content');

    $run->refresh();
    expect($run->generated_post_id)->not->toBeNull();
});

it('derives carousel format when a carousel-capable account is configured with target_slide_count > 1', function () {
    $workspace = Workspace::factory()->create();
    $account = SocialAccount::factory()->for($workspace)->create(['platform' => 'instagram']);

    $action = app(RunGenerateNode::class);

    $accountsConfig = [
        ['social_account_id' => (string) $account->id, 'content_type' => ContentType::InstagramFeed->value, 'meta' => []],
    ];

    ['format' => $format, 'slide_count' => $slideCount] = $action->deriveFormat($accountsConfig, ['target_slide_count' => 5]);

    expect($format)->toBe('carousel');
    expect($slideCount)->toBe(5);
});

it('derives single format when carousel account has target_slide_count of 1', function () {
    $workspace = Workspace::factory()->create();
    $account = SocialAccount::factory()->for($workspace)->create(['platform' => 'instagram']);

    $action = app(RunGenerateNode::class);

    $accountsConfig = [
        ['social_account_id' => (string) $account->id, 'content_type' => ContentType::InstagramFeed->value, 'meta' => []],
    ];

    ['format' => $format, 'slide_count' => $slideCount] = $action->deriveFormat($accountsConfig, ['target_slide_count' => 1]);

    expect($format)->toBe('single');
    expect($slideCount)->toBe(1);
});

it('derives single format when no carousel-capable account is configured', function () {
    $action = app(RunGenerateNode::class);

    $accountsConfig = [
        ['social_account_id' => '1', 'content_type' => ContentType::XPost->value, 'meta' => []],
        ['social_account_id' => '2', 'content_type' => ContentType::InstagramReel->value, 'meta' => []],
    ];

    ['format' => $format, 'slide_count' => $slideCount] = $action->deriveFormat($accountsConfig, ['target_slide_count' => 5]);

    expect($format)->toBe('single');
    expect($slideCount)->toBe(1);
});
