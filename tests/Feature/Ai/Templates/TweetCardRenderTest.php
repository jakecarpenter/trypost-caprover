<?php

declare(strict_types=1);

use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Services\Image\PostImagePipeline;
use App\Services\Image\TemplateImageGenerator;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Image;

test('renderTweetCard produces a stored webp without calling the AI image client', function () {
    Storage::fake();
    Image::fake();
    $workspace = Workspace::factory()->create(['brand_color' => '#1d9bf0']);
    $account = SocialAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'display_name' => 'Alan Nicolas',
        'username' => 'oalanicolas',
    ]);

    $result = app(TemplateImageGenerator::class)->renderTweetCard(
        $workspace,
        $account,
        "Aí cai a ficha.\n\nIsso tem nome: você está sendo subsidiado.",
    );

    expect($result)->not->toBeNull()
        ->and($result['path'])->toEndWith('.webp')
        ->and(Storage::exists($result['path']))->toBeTrue()
        ->and(data_get($result, 'source_meta.template'))->toBe('tweet_card');

    Image::assertNothingGenerated();
});

test('renderTweetCard succeeds when the account has no avatar_url', function () {
    Storage::fake();
    Image::fake();
    $workspace = Workspace::factory()->create(['brand_color' => '#1d9bf0']);
    $account = SocialAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'display_name' => 'No Avatar User',
        'username' => 'noavatar',
        'avatar_url' => null,
    ]);

    $result = app(TemplateImageGenerator::class)->renderTweetCard(
        $workspace,
        $account,
        'A tweet with no avatar attached.',
    );

    expect($result)->not->toBeNull()
        ->and($result['path'])->toEndWith('.webp')
        ->and(Storage::exists($result['path']))->toBeTrue();
});

test('forTweetCard returns [] when renderTweetCard returns null', function () {
    Storage::fake();

    $workspace = Workspace::factory()->create();
    $account = SocialAccount::factory()->create(['workspace_id' => $workspace->id]);

    $mock = Mockery::mock(TemplateImageGenerator::class)->makePartial();
    $mock->shouldReceive('renderTweetCard')->andReturn(null);

    $this->app->instance(TemplateImageGenerator::class, $mock);

    $result = app(PostImagePipeline::class)->forTweetCard($workspace, $account, 'some text');

    expect($result)->toBe([]);
});
