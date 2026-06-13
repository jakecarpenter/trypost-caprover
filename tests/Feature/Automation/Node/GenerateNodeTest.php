<?php

declare(strict_types=1);

use App\Actions\Automation\Node\RunGenerateNode;
use App\Ai\Agents\PostContentGenerator;
use App\Ai\Agents\PostContentHumanizer;
use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\ContentType;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Media;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Services\Image\PostImagePipeline;

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

it('applies brand voice by default', function () {
    PostContentGenerator::fake([
        ['content' => 'c', 'image_title' => 't', 'image_body' => 'b', 'image_keywords' => ['k']],
    ]);
    PostContentHumanizer::fake([['content' => 'c', 'image_title' => 't', 'image_body' => 'b']]);

    $run = AutomationRun::factory()->create(['context' => ['trigger' => ['title' => 'News']]]);

    app(RunGenerateNode::class)($run, [
        'accounts' => [],
        'prompt_template' => 'Write about {{ trigger.title }}',
    ]);

    PostContentGenerator::assertPrompted(fn ($prompt) => $prompt->agent->applyBrandVoice === true);
    PostContentHumanizer::assertPrompted(fn ($prompt) => $prompt->agent->applyBrandVoice === true);
});

it('skips brand voice when use_brand_voice is off', function () {
    PostContentGenerator::fake([
        ['content' => 'c', 'image_title' => 't', 'image_body' => 'b', 'image_keywords' => ['k']],
    ]);
    PostContentHumanizer::fake([['content' => 'c', 'image_title' => 't', 'image_body' => 'b']]);

    $run = AutomationRun::factory()->create(['context' => ['trigger' => ['title' => 'OpenAI news']]]);

    app(RunGenerateNode::class)($run, [
        'accounts' => [],
        'prompt_template' => 'Write about {{ trigger.title }}',
        'use_brand_voice' => false,
    ]);

    PostContentGenerator::assertPrompted(fn ($prompt) => $prompt->agent->applyBrandVoice === false);
    PostContentHumanizer::assertPrompted(fn ($prompt) => $prompt->agent->applyBrandVoice === false);
});

it('passes the selected network as the generator platform context', function () {
    PostContentGenerator::fake([
        ['content' => 'c', 'image_title' => 't', 'image_body' => 'b', 'image_keywords' => ['k']],
    ]);
    PostContentHumanizer::fake([['content' => 'c', 'image_title' => 't', 'image_body' => 'b']]);

    $workspace = Workspace::factory()->create();
    $account = SocialAccount::factory()->for($workspace)->create(['platform' => 'linkedin']);
    $run = AutomationRun::factory()->for(Automation::factory()->for($workspace))->create([
        'context' => ['trigger' => ['title' => 'News']],
    ]);

    app(RunGenerateNode::class)($run, [
        'accounts' => [['social_account_id' => (string) $account->id, 'content_type' => ContentType::LinkedInPost->value]],
        'prompt_template' => 'Write about {{ trigger.title }}',
    ]);

    PostContentGenerator::assertPrompted(fn ($prompt) => $prompt->agent->platformContext === ContentType::LinkedInPost->value);
});

it('writes for the most restrictive network when several are selected', function () {
    PostContentGenerator::fake([
        ['content' => 'c', 'image_title' => 't', 'image_body' => 'b', 'image_keywords' => ['k']],
    ]);
    PostContentHumanizer::fake([['content' => 'c', 'image_title' => 't', 'image_body' => 'b']]);

    $workspace = Workspace::factory()->create();
    $linkedin = SocialAccount::factory()->for($workspace)->create(['platform' => 'linkedin']);
    $x = SocialAccount::factory()->for($workspace)->create(['platform' => 'x']);
    $run = AutomationRun::factory()->for(Automation::factory()->for($workspace))->create([
        'context' => ['trigger' => ['title' => 'News']],
    ]);

    // X caps at 280 chars, LinkedIn at 3000 — copy must fit the tighter one.
    app(RunGenerateNode::class)($run, [
        'accounts' => [
            ['social_account_id' => (string) $linkedin->id, 'content_type' => ContentType::LinkedInPost->value],
            ['social_account_id' => (string) $x->id, 'content_type' => ContentType::XPost->value],
        ],
        'prompt_template' => 'Write about {{ trigger.title }}',
    ]);

    PostContentGenerator::assertPrompted(fn ($prompt) => $prompt->agent->platformContext === ContentType::XPost->value);
    // The humanizer second pass gets the same context so it can't rewrite the
    // copy back over the platform's character cap.
    PostContentHumanizer::assertPrompted(fn ($prompt) => $prompt->agent->platformContext === ContentType::XPost->value);
});

it('leaves the platform context null when no account carries a content type', function () {
    PostContentGenerator::fake([
        ['content' => 'c', 'image_title' => 't', 'image_body' => 'b', 'image_keywords' => ['k']],
    ]);
    PostContentHumanizer::fake([['content' => 'c', 'image_title' => 't', 'image_body' => 'b']]);

    $workspace = Workspace::factory()->create();
    $account = SocialAccount::factory()->for($workspace)->create(['platform' => 'x']);
    $run = AutomationRun::factory()->for(Automation::factory()->for($workspace))->create([
        'context' => ['trigger' => ['title' => 'News']],
    ]);

    app(RunGenerateNode::class)($run, [
        'accounts' => [['social_account_id' => (string) $account->id]],
        'prompt_template' => 'Write about {{ trigger.title }}',
    ]);

    PostContentGenerator::assertPrompted(fn ($prompt) => $prompt->agent->platformContext === null);
});

it('still resolves the most restrictive context when the format is a carousel', function () {
    PostContentGenerator::fake([
        ['caption' => 'cap', 'slides' => [
            ['title' => 'S1', 'body' => 'B1', 'image_keywords' => ['a']],
            ['title' => 'S2', 'body' => 'B2', 'image_keywords' => ['b']],
        ]],
    ]);
    PostContentHumanizer::fake([
        ['caption' => 'cap', 'slides' => [['title' => 'S1', 'body' => 'B1'], ['title' => 'S2', 'body' => 'B2']]],
    ]);

    $workspace = Workspace::factory()->create();
    $instagram = SocialAccount::factory()->for($workspace)->create(['platform' => 'instagram']);
    $x = SocialAccount::factory()->for($workspace)->create(['platform' => 'x']);
    $run = AutomationRun::factory()->for(Automation::factory()->for($workspace))->create([
        'context' => ['trigger' => ['title' => 'News']],
    ]);

    // Instagram feed is carousel-capable, so the format is a carousel, yet the
    // platform context must still be X (280) — the tightest selected network.
    app(RunGenerateNode::class)($run, [
        'accounts' => [
            ['social_account_id' => (string) $instagram->id, 'content_type' => ContentType::InstagramFeed->value],
            ['social_account_id' => (string) $x->id, 'content_type' => ContentType::XPost->value],
        ],
        'prompt_template' => 'Write about {{ trigger.title }}',
        'target_slide_count' => 2,
    ]);

    PostContentGenerator::assertPrompted(fn ($prompt) => $prompt->agent->format === 'carousel'
        && $prompt->agent->platformContext === ContentType::XPost->value);
});

it('leaves the platform context null for the legacy social_account_ids shape', function () {
    PostContentGenerator::fake([
        ['content' => 'c', 'image_title' => 't', 'image_body' => 'b', 'image_keywords' => ['k']],
    ]);
    PostContentHumanizer::fake([['content' => 'c', 'image_title' => 't', 'image_body' => 'b']]);

    $workspace = Workspace::factory()->create();
    $account = SocialAccount::factory()->for($workspace)->create(['platform' => 'x']);
    $run = AutomationRun::factory()->for(Automation::factory()->for($workspace))->create([
        'context' => ['trigger' => ['title' => 'News']],
    ]);

    app(RunGenerateNode::class)($run, [
        'social_account_ids' => [(string) $account->id],
        'prompt_template' => 'Write about {{ trigger.title }}',
    ]);

    PostContentGenerator::assertPrompted(fn ($prompt) => $prompt->agent->platformContext === null);
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
        ['social_account_id' => '1', 'content_type' => ContentType::LinkedInPost->value, 'meta' => []],
        ['social_account_id' => '2', 'content_type' => ContentType::InstagramReel->value, 'meta' => []],
    ];

    ['format' => $format, 'slide_count' => $slideCount] = $action->deriveFormat($accountsConfig, ['target_slide_count' => 5]);

    expect($format)->toBe('single');
    expect($slideCount)->toBe(1);
});

it('attaches a generated image to a single-format post', function () {
    PostContentGenerator::fake([
        ['content' => 'Single post', 'image_title' => 'Title', 'image_body' => 'Body', 'image_keywords' => ['kw']],
    ]);

    PostContentHumanizer::fake([
        ['content' => 'Single post', 'image_title' => 'Title', 'image_body' => 'Body'],
    ]);

    $workspace = Workspace::factory()->create();
    $account = SocialAccount::factory()->for($workspace)->create(['platform' => 'x']);

    $mediaItem = ['id' => 1, 'path' => 'ai-images/x.webp', 'url' => 'http://x', 'type' => 'image', 'mime_type' => 'image/webp', 'source' => 'ai', 'source_meta' => []];

    $pipeline = Mockery::mock(PostImagePipeline::class);
    $pipeline->shouldReceive('forSingle')->once()->andReturn([$mediaItem]);
    app()->instance(PostImagePipeline::class, $pipeline);

    $automation = Automation::factory()->for($workspace)->create();
    $run = AutomationRun::factory()->for($automation)->create([
        'context' => ['trigger' => ['title' => 'test']],
    ]);

    $result = app(RunGenerateNode::class)($run, [
        'accounts' => [
            ['social_account_id' => (string) $account->id, 'content_type' => ContentType::XPost->value, 'meta' => []],
        ],
        'prompt_template' => 'Write about {{ trigger.title }}',
        'target_slide_count' => 1,
    ]);

    expect($result->status->value)->toBe('completed');

    $post = Post::find($result->output['generated']['post_id']);
    expect($post)->not->toBeNull();
    expect($post->media)->toHaveCount(1);
});

it('attaches one image per slide for carousel', function () {
    PostContentGenerator::fake([
        ['caption' => 'Carousel caption', 'slides' => [
            ['title' => 'S1', 'body' => 'B1', 'image_keywords' => ['a']],
            ['title' => 'S2', 'body' => 'B2', 'image_keywords' => ['b']],
            ['title' => 'S3', 'body' => 'B3', 'image_keywords' => ['c']],
        ]],
    ]);

    PostContentHumanizer::fake([
        ['caption' => 'Carousel caption', 'slides' => [
            ['title' => 'S1', 'body' => 'B1'],
            ['title' => 'S2', 'body' => 'B2'],
            ['title' => 'S3', 'body' => 'B3'],
        ]],
    ]);

    $workspace = Workspace::factory()->create();
    $account = SocialAccount::factory()->for($workspace)->create(['platform' => 'instagram']);

    $item = fn (int $id) => ['id' => $id, 'path' => "ai-images/{$id}.webp", 'url' => "http://{$id}", 'type' => 'image', 'mime_type' => 'image/webp', 'source' => 'ai', 'source_meta' => []];

    $pipeline = Mockery::mock(PostImagePipeline::class);
    $pipeline->shouldReceive('forCarousel')->once()->andReturn([$item(1), $item(2), $item(3)]);
    app()->instance(PostImagePipeline::class, $pipeline);

    $automation = Automation::factory()->for($workspace)->create();
    $run = AutomationRun::factory()->for($automation)->create([
        'context' => ['trigger' => ['title' => 'test']],
    ]);

    $result = app(RunGenerateNode::class)($run, [
        'accounts' => [
            ['social_account_id' => (string) $account->id, 'content_type' => ContentType::InstagramFeed->value, 'meta' => []],
        ],
        'prompt_template' => 'Write about {{ trigger.title }}',
        'target_slide_count' => 3,
    ]);

    expect($result->status->value)->toBe('completed');

    $post = Post::find($result->output['generated']['post_id']);
    expect($post)->not->toBeNull();
    expect($post->media)->toHaveCount(3);
});

it('skips images when image count is zero', function () {
    PostContentGenerator::fake([
        ['content' => 'No image post', 'image_title' => 'Title', 'image_body' => 'Body', 'image_keywords' => ['kw']],
    ]);

    PostContentHumanizer::fake([
        ['content' => 'No image post', 'image_title' => 'Title', 'image_body' => 'Body'],
    ]);

    $workspace = Workspace::factory()->create();
    $account = SocialAccount::factory()->for($workspace)->create(['platform' => 'x']);

    $pipeline = Mockery::mock(PostImagePipeline::class);
    $pipeline->shouldNotReceive('forSingle');
    $pipeline->shouldNotReceive('forCarousel');
    app()->instance(PostImagePipeline::class, $pipeline);

    $automation = Automation::factory()->for($workspace)->create();
    $run = AutomationRun::factory()->for($automation)->create([
        'context' => ['trigger' => ['title' => 'test']],
    ]);

    $result = app(RunGenerateNode::class)($run, [
        'accounts' => [
            ['social_account_id' => (string) $account->id, 'content_type' => ContentType::XPost->value, 'meta' => []],
        ],
        'prompt_template' => 'Write about {{ trigger.title }}',
        'target_slide_count' => 0,
    ]);

    expect($result->status->value)->toBe('completed');

    $post = Post::find($result->output['generated']['post_id']);
    expect($post)->not->toBeNull();
    expect($post->media)->toHaveCount(0);
});

it('does not generate images or persist on a dry run', function () {
    PostContentGenerator::fake([
        ['content' => 'Dry run post', 'image_title' => 'Title', 'image_body' => 'Body', 'image_keywords' => ['kw']],
    ]);

    PostContentHumanizer::fake([
        ['content' => 'Dry run post', 'image_title' => 'Title', 'image_body' => 'Body'],
    ]);

    $workspace = Workspace::factory()->create();
    $account = SocialAccount::factory()->for($workspace)->create(['platform' => 'x']);

    $pipeline = Mockery::mock(PostImagePipeline::class);
    $pipeline->shouldNotReceive('forSingle');
    $pipeline->shouldNotReceive('forCarousel');
    app()->instance(PostImagePipeline::class, $pipeline);

    $automation = Automation::factory()->for($workspace)->create();
    $run = AutomationRun::factory()->for($automation)->create([
        'is_dry_run' => true,
        'context' => ['trigger' => ['title' => 'test']],
    ]);

    $result = app(RunGenerateNode::class)($run, [
        'accounts' => [
            ['social_account_id' => (string) $account->id, 'content_type' => ContentType::XPost->value, 'meta' => []],
        ],
        'prompt_template' => 'Write about {{ trigger.title }}',
        'target_slide_count' => 1,
    ]);

    expect($result->status->value)->toBe('completed');
    expect($result->output['generated']['dry_run'])->toBeTrue();
    expect($result->output['generated']['content'])->toBe('Dry run post');
    expect($result->output['generated']['image_count'])->toBe(1);
    expect($result->output['generated']['post_id'])->toBeNull();

    expect(Post::count())->toBe(0);
    expect(Media::count())->toBe(0);

    $run->refresh();
    expect($run->generated_post_id)->toBeNull();
});
