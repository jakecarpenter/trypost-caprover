<?php

declare(strict_types=1);

use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Jobs\PublishPost;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $result = createApiTestToken();
    $this->user = $result['user'];
    $this->workspace = $result['workspace'];
    $this->plainToken = $result['plain_token'];

    $this->headers = ['Authorization' => 'Bearer '.$this->plainToken];

    $this->discordAccount = SocialAccount::factory()->discord()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => '111222333',
    ]);
});

it('persists Discord channel, mentions and embeds meta on store', function () {
    $this->withHeaders($this->headers)
        ->postJson(route('api.posts.store'), [
            'content' => 'Hello Discord',
            'platforms' => [[
                'social_account_id' => $this->discordAccount->id,
                'content_type' => ContentType::DiscordMessage->value,
                'meta' => [
                    'channel_id' => '444555666',
                    'mentions' => [['token' => '@everyone', 'label' => '@everyone']],
                    'embeds' => [['title' => 'Release', 'color' => '#5865F2']],
                ],
            ]],
        ])
        ->assertCreated();

    $meta = PostPlatform::where('social_account_id', $this->discordAccount->id)->sole()->meta;

    expect($meta['channel_id'])->toBe('444555666')
        ->and($meta['mentions'])->toHaveCount(1)
        ->and($meta['embeds.0.title'] ?? data_get($meta, 'embeds.0.title'))->toBe('Release');
});

it('persists per-platform meta across networks on store', function () {
    $instagram = SocialAccount::factory()->create(['workspace_id' => $this->workspace->id, 'platform' => Platform::Instagram]);
    $pinterest = SocialAccount::factory()->create(['workspace_id' => $this->workspace->id, 'platform' => Platform::Pinterest]);
    $tiktok = SocialAccount::factory()->create(['workspace_id' => $this->workspace->id, 'platform' => Platform::TikTok]);

    $this->withHeaders($this->headers)
        ->postJson(route('api.posts.store'), [
            'content' => 'Cross-platform',
            'platforms' => [
                ['social_account_id' => $instagram->id, 'content_type' => ContentType::InstagramFeed->value, 'meta' => ['aspect_ratio' => '4:5']],
                ['social_account_id' => $pinterest->id, 'content_type' => ContentType::PinterestPin->value, 'meta' => ['board_id' => 'board-99']],
                ['social_account_id' => $tiktok->id, 'content_type' => ContentType::TikTokVideo->value, 'meta' => ['privacy_level' => 'SELF_ONLY', 'allow_comments' => true]],
            ],
        ])
        ->assertCreated();

    expect(PostPlatform::where('social_account_id', $instagram->id)->sole()->meta['aspect_ratio'])->toBe('4:5')
        ->and(PostPlatform::where('social_account_id', $pinterest->id)->sole()->meta['board_id'])->toBe('board-99')
        ->and(PostPlatform::where('social_account_id', $tiktok->id)->sole()->meta['privacy_level'])->toBe('SELF_ONLY')
        ->and(PostPlatform::where('social_account_id', $tiktok->id)->sole()->meta['allow_comments'])->toBeTrue();
});

it('allows saving a Discord draft without a channel', function () {
    $post = Post::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->user->id]);
    $platform = PostPlatform::factory()->discord()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->discordAccount->id,
        'enabled' => true,
        'meta' => [],
    ]);

    $this->withHeaders($this->headers)
        ->putJson(route('api.posts.update', $post), [
            'status' => PostStatus::Draft->value,
            'platforms' => [['id' => $platform->id]],
        ])
        ->assertOk();
});

it('rejects publishing without TikTok privacy and Pinterest board', function () {
    $pinterest = SocialAccount::factory()->create(['workspace_id' => $this->workspace->id, 'platform' => Platform::Pinterest]);
    $tiktok = SocialAccount::factory()->create(['workspace_id' => $this->workspace->id, 'platform' => Platform::TikTok]);

    $post = Post::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->user->id]);
    $pinterestPlatform = PostPlatform::factory()->pinterest()->create([
        'post_id' => $post->id, 'social_account_id' => $pinterest->id, 'enabled' => true, 'meta' => [],
    ]);
    $tiktokPlatform = PostPlatform::factory()->tiktok()->create([
        'post_id' => $post->id, 'social_account_id' => $tiktok->id, 'enabled' => true, 'meta' => [],
    ]);

    $this->withHeaders($this->headers)
        ->putJson(route('api.posts.update', $post), [
            'status' => PostStatus::Publishing->value,
            'platforms' => [
                ['id' => $pinterestPlatform->id],
                ['id' => $tiktokPlatform->id],
            ],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'platforms.0.meta.board_id',
            'platforms.1.meta.privacy_level',
        ]);
});

it('rejects publishing a Discord post without a channel', function () {
    $post = Post::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->user->id]);
    $platform = PostPlatform::factory()->discord()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->discordAccount->id,
        'enabled' => true,
        'meta' => [],
    ]);

    $this->withHeaders($this->headers)
        ->putJson(route('api.posts.update', $post), [
            'status' => PostStatus::Publishing->value,
            'platforms' => [['id' => $platform->id]],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['platforms.0.meta.channel_id']);
});

it('publishes a Discord post when the channel is set', function () {
    Queue::fake();

    $post = Post::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->user->id]);
    $platform = PostPlatform::factory()->discord()->create([
        'post_id' => $post->id,
        'social_account_id' => $this->discordAccount->id,
        'enabled' => true,
        'meta' => [],
    ]);

    $this->withHeaders($this->headers)
        ->putJson(route('api.posts.update', $post), [
            'status' => PostStatus::Publishing->value,
            'platforms' => [['id' => $platform->id, 'meta' => ['channel_id' => '444555666']]],
        ])
        ->assertOk();

    expect($platform->fresh()->meta['channel_id'])->toBe('444555666');
    Queue::assertPushed(PublishPost::class);
});
