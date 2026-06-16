<?php

declare(strict_types=1);

namespace App\Services\Social\Discord;

use App\Models\PostPlatform;
use App\Models\SocialAccount;
use Throwable;

/**
 * Discord engagement metrics. Discord exposes no impressions/reach/views for
 * bot messages, so the signals are the server member count (account-level) and,
 * per post, reaction counts and thread replies — read live with the bot token.
 */
class DiscordAnalytics
{
    public function __construct(private DiscordClient $client) {}

    /**
     * Account-level metric: the server's approximate member count.
     *
     * @return array<int, array{label: string, value: int}>
     */
    public function getMetrics(SocialAccount $account): array
    {
        $guildId = (string) $account->platform_user_id;

        if ($guildId === '') {
            return [];
        }

        try {
            $response = $this->client->getGuild($guildId);
        } catch (Throwable) {
            return [];
        }

        if ($response->failed()) {
            return [];
        }

        $count = data_get($response->json(), 'approximate_member_count');

        if (! is_int($count)) {
            return [];
        }

        return [
            ['label' => __('analytics.metrics.members'), 'value' => $count],
        ];
    }

    /**
     * Post-level metrics: thread replies plus reaction counts per emoji, tagged
     * so the UI renders them as pills alongside the member count.
     *
     * @return array<int, array{label: string, value: int, kind?: string}>
     */
    public function fetchPostMetrics(PostPlatform $postPlatform): array
    {
        $account = $postPlatform->socialAccount;
        $metrics = $account
            ? array_map(fn (array $metric): array => [...$metric, 'kind' => 'subscribers'], $this->getMetrics($account))
            : [];

        $channelId = (string) data_get($postPlatform->meta, 'channel_id');
        $messageId = (string) $postPlatform->platform_post_id;

        if ($channelId === '' || $messageId === '') {
            return $metrics;
        }

        try {
            $response = $this->client->getMessage($channelId, $messageId);
        } catch (Throwable) {
            return $metrics;
        }

        if ($response->failed()) {
            return $metrics;
        }

        $message = $response->json();

        foreach ((array) data_get($message, 'reactions', []) as $reaction) {
            $name = (string) data_get($reaction, 'emoji.name');
            $label = data_get($reaction, 'emoji.id') ? ":{$name}:" : $name;

            $metrics[] = [
                'label' => $label !== '' ? $label : __('analytics.metrics.custom_reaction'),
                'value' => (int) data_get($reaction, 'count'),
                'kind' => 'reaction',
            ];
        }

        // Thread replies are their own metric (kind "comments") so the UI renders
        // them as a 💬 pill next to the member count, with a distinguishing tooltip.
        $replies = (int) data_get($message, 'thread.message_count', 0);

        if ($replies > 0) {
            $metrics[] = ['label' => __('analytics.metrics.comments'), 'value' => $replies, 'kind' => 'comments'];
        }

        return $metrics;
    }
}
