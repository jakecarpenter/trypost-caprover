<?php

declare(strict_types=1);

use App\Ai\Agents\PostContentGenerator;
use App\Ai\Agents\PostContentHumanizer;
use App\Enums\PostPlatform\ContentType;
use App\Enums\UserWorkspace\Role;
use App\Jobs\Ai\StreamPostCreation;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Image;

beforeEach(function () {
    Bus::fake();
    Storage::fake();
    Image::fake();

    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $this->account = SocialAccount::factory()->instagram()->create([
        'workspace_id' => $this->workspace->id,
    ]);
});

function runStreamPostCreation(string $format, SocialAccount $account, int $imageCount): void
{
    (new StreamPostCreation(
        userId: $account->workspace->user_id,
        creationId: (string) Str::uuid(),
        workspaceId: $account->workspace_id,
        format: $format,
        socialAccountId: $account->id,
        imageCount: $imageCount,
        prompt: 'Five tips about productivity',
    ))->handle();
}

test('AI carousel generation stores the post as an instagram feed, never instagram_carousel', function () {
    // Empty image_keywords make TemplateImageGenerator::render() return null, so
    // the storage decision is exercised without touching the image pipeline.
    PostContentGenerator::fake([[
        'caption' => 'Swipe to see the tips',
        'slides' => [
            ['title' => 'Tip 1', 'body' => 'First tip', 'image_keywords' => []],
            ['title' => 'Tip 2', 'body' => 'Second tip', 'image_keywords' => []],
        ],
    ]]);
    PostContentHumanizer::fake([[
        'caption' => 'Swipe to see the tips',
        'slides' => [
            ['title' => 'Tip 1', 'body' => 'First tip'],
            ['title' => 'Tip 2', 'body' => 'Second tip'],
        ],
    ]]);

    runStreamPostCreation('instagram_carousel', $this->account, 2);

    $platform = PostPlatform::where('social_account_id', $this->account->id)->firstOrFail();

    expect($platform->content_type)->toBe(ContentType::InstagramFeed);
    expect($platform->meta['aspect_ratio'] ?? null)->toBe('4:5');
});

test('AI single feed generation stores the post as an instagram feed', function () {
    PostContentGenerator::fake([[
        'content' => 'A single productivity tip',
        'image_title' => 'Tip',
        'image_body' => 'Do less',
        'image_keywords' => [],
    ]]);
    PostContentHumanizer::fake([[
        'content' => 'A single productivity tip',
        'image_title' => 'Tip',
        'image_body' => 'Do less',
    ]]);

    runStreamPostCreation('instagram_feed', $this->account, 0);

    $platform = PostPlatform::where('social_account_id', $this->account->id)->firstOrFail();

    expect($platform->content_type)->toBe(ContentType::InstagramFeed);
});
