<?php

declare(strict_types=1);

use App\Enums\PostPlatform\Status;
use App\Enums\SocialAccount\Platform;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Services\Social\Discord\DiscordAnalytics;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['trypost.platforms.discord.bot_token' => 'BOTTOKEN']);
});

it('returns the server member count as an account metric', function () {
    $account = SocialAccount::factory()->discord()->create(['platform_user_id' => '111222333']);

    Http::fake([
        config('trypost.platforms.discord.api').'/guilds/111222333*' => Http::response(['id' => '111222333', 'approximate_member_count' => 4200], 200),
    ]);

    expect(app(DiscordAnalytics::class)->getMetrics($account))
        ->toBe([['label' => 'Members', 'value' => 4200]]);
});

it('returns no account metrics when the guild lookup fails', function () {
    $account = SocialAccount::factory()->discord()->create(['platform_user_id' => '111222333']);

    Http::fake([
        config('trypost.platforms.discord.api').'/guilds/111222333*' => Http::response(['message' => 'Unknown Guild'], 404),
    ]);

    expect(app(DiscordAnalytics::class)->getMetrics($account))->toBe([]);
});

it('maps message reactions and thread replies to post metrics', function () {
    $account = SocialAccount::factory()->discord()->create(['platform_user_id' => '111222333']);
    $postPlatform = PostPlatform::factory()->create([
        'social_account_id' => $account->id,
        'platform' => Platform::Discord,
        'status' => Status::Published,
        'platform_post_id' => '777',
        'meta' => ['channel_id' => '444555666'],
    ]);

    Http::fake([
        config('trypost.platforms.discord.api').'/guilds/111222333*' => Http::response(['approximate_member_count' => 50], 200),
        config('trypost.platforms.discord.api').'/channels/444555666/messages/777' => Http::response([
            'id' => '777',
            'thread' => ['message_count' => 8],
            'reactions' => [
                ['count' => 12, 'emoji' => ['id' => null, 'name' => '🔥']],
                ['count' => 3, 'emoji' => ['id' => '999', 'name' => 'partyblob']],
            ],
        ], 200),
    ]);

    expect(app(DiscordAnalytics::class)->fetchPostMetrics($postPlatform))
        ->toBe([
            ['label' => 'Members', 'value' => 50, 'kind' => 'subscribers'],
            ['label' => '🔥', 'value' => 12, 'kind' => 'reaction'],
            ['label' => ':partyblob:', 'value' => 3, 'kind' => 'reaction'],
            ['label' => 'Comments', 'value' => 8, 'kind' => 'comments'],
        ]);
});

it('returns only the member count when the message has no engagement yet', function () {
    $account = SocialAccount::factory()->discord()->create(['platform_user_id' => '111222333']);
    $postPlatform = PostPlatform::factory()->create([
        'social_account_id' => $account->id,
        'platform' => Platform::Discord,
        'status' => Status::Published,
        'platform_post_id' => '777',
        'meta' => ['channel_id' => '444555666'],
    ]);

    Http::fake([
        config('trypost.platforms.discord.api').'/guilds/111222333*' => Http::response(['approximate_member_count' => 50], 200),
        config('trypost.platforms.discord.api').'/channels/444555666/messages/777' => Http::response(['id' => '777'], 200),
    ]);

    expect(app(DiscordAnalytics::class)->fetchPostMetrics($postPlatform))
        ->toBe([['label' => 'Members', 'value' => 50, 'kind' => 'subscribers']]);
});
