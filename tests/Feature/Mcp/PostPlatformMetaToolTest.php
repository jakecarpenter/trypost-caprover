<?php

declare(strict_types=1);

use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Enums\UserWorkspace\Role;
use App\Jobs\PublishPost;
use App\Mcp\Servers\TryPostServer;
use App\Mcp\Tools\Post\CreatePostTool;
use App\Mcp\Tools\Post\PublishPostTool;
use App\Mcp\Tools\Post\UpdatePostTool;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $this->discordAccount = SocialAccount::factory()->discord()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => '111222333',
    ]);
});

test('create post persists Discord channel + embeds meta', function () {
    $response = TryPostServer::actingAs($this->user)
        ->tool(CreatePostTool::class, [
            'content' => 'Hello Discord',
            'platforms' => [[
                'social_account_id' => $this->discordAccount->id,
                'content_type' => ContentType::DiscordMessage->value,
                'meta' => [
                    'channel_id' => '444555666',
                    'embeds' => [['title' => 'Release']],
                ],
            ]],
        ]);

    $response->assertOk();

    $meta = PostPlatform::where('social_account_id', $this->discordAccount->id)->sole()->meta;

    expect($meta['channel_id'])->toBe('444555666')
        ->and(data_get($meta, 'embeds.0.title'))->toBe('Release');
});

test('update post merges per-platform meta', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Draft,
    ]);
    $platform = PostPlatform::factory()->discord()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->discordAccount->id,
        'enabled' => true,
        'meta' => ['channel_name' => 'general'],
    ]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(UpdatePostTool::class, [
            'post_id' => $post->id,
            'platforms' => [[
                'id' => $platform->id,
                'meta' => ['channel_id' => '444555666'],
            ]],
        ]);

    $response->assertOk();

    $meta = $platform->fresh()->meta;
    expect($meta['channel_id'])->toBe('444555666')
        ->and($meta['channel_name'])->toBe('general'); // merged, not overwritten
});

test('publish guard ignores disabled platforms missing meta', function () {
    Queue::fake();

    $linkedin = SocialAccount::factory()->create(['workspace_id' => $this->workspace->id, 'platform' => Platform::LinkedIn]);

    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Draft,
    ]);
    PostPlatform::factory()->linkedin()->create([
        'post_id' => $post->id,
        'social_account_id' => $linkedin->id,
        'enabled' => true,
    ]);
    // Disabled Discord with no channel must not block the publish.
    PostPlatform::factory()->discord()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->discordAccount->id,
        'enabled' => false,
        'meta' => [],
    ]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(PublishPostTool::class, ['post_id' => $post->id]);

    $response->assertOk();
    Queue::assertPushed(PublishPost::class);
});

test('publish post rejects a Discord platform without a channel', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Draft,
    ]);
    PostPlatform::factory()->discord()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->discordAccount->id,
        'enabled' => true,
        'meta' => [],
    ]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(PublishPostTool::class, ['post_id' => $post->id]);

    $response->assertHasErrors([__('posts.form.discord.channel_required')]);
});

test('publish guard enforces required meta for TikTok and Pinterest', function (string $factoryState, string $field, string $messageKey) {
    $account = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => $factoryState === 'tiktok' ? Platform::TikTok : Platform::Pinterest,
    ]);

    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Draft,
    ]);
    PostPlatform::factory()->{$factoryState}()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
        'enabled' => true,
        'meta' => [],
    ]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(PublishPostTool::class, ['post_id' => $post->id]);

    $response->assertHasErrors([__($messageKey)]);
})->with([
    'tiktok' => ['tiktok', 'privacy_level', 'posts.form.tiktok.privacy_required'],
    'pinterest' => ['pinterest', 'board_id', 'posts.form.pinterest.board_required'],
]);

test('publish post succeeds for a Discord platform with a channel', function () {
    Queue::fake();

    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::Draft,
    ]);
    PostPlatform::factory()->discord()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->discordAccount->id,
        'enabled' => true,
        'meta' => ['channel_id' => '444555666'],
    ]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(PublishPostTool::class, ['post_id' => $post->id]);

    $response->assertOk();
    Queue::assertPushed(PublishPost::class);
});
