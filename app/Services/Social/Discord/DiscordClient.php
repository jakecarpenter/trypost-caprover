<?php

declare(strict_types=1);

namespace App\Services\Social\Discord;

use App\Exceptions\PlatformUnavailableException;
use App\Services\Social\Concerns\HasSocialHttpClient;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * Read-side Discord API calls made with the global bot token (channel listing,
 * mention search, guild verification). Posting lives in DiscordPublisher.
 */
class DiscordClient
{
    use HasSocialHttpClient;

    /**
     * Text-postable channel types: 0 = text, 5 = announcement, 15 = forum.
     */
    private const POSTABLE_CHANNEL_TYPES = [0, 5, 15];

    public function baseUrl(): string
    {
        return (string) config('trypost.platforms.discord.api');
    }

    /**
     * The text channels of a guild the bot can post into.
     *
     * @return list<array{id: string, name: string}>
     *
     * @throws PlatformUnavailableException when the lookup fails transiently, so
     *                                      callers (e.g. the publish-time channel guard) retry instead of
     *                                      treating an empty result as "channel not found".
     */
    public function channels(string $guildId): array
    {
        $response = $this->bot()->get("{$this->baseUrl()}/guilds/{$guildId}/channels");

        if ($response->failed()) {
            throw new PlatformUnavailableException("Discord channel lookup failed ({$response->status()}).", $response->status());
        }

        $channels = $response->json();

        if (! is_array($channels)) {
            return [];
        }

        return collect($channels)
            ->filter(fn ($channel) => in_array((int) data_get($channel, 'type'), self::POSTABLE_CHANNEL_TYPES, true))
            ->map(fn ($channel) => [
                'id' => (string) data_get($channel, 'id'),
                'name' => (string) data_get($channel, 'name'),
            ])
            ->values()
            ->all();
    }

    /**
     * Mentionable targets matching a query: @everyone/@here, roles, then members.
     *
     * @return list<array{id: string, label: string, type: string}>
     */
    public function mentions(string $guildId, string $query): array
    {
        $query = trim($query);
        $needle = mb_strtolower($query);

        $specials = collect([
            ['id' => 'everyone', 'label' => '@everyone', 'type' => 'everyone'],
            ['id' => 'here', 'label' => '@here', 'type' => 'here'],
        ])->filter(fn ($item) => $needle === '' || str_contains($item['label'], $needle));

        $roles = collect($this->getList("{$this->baseUrl()}/guilds/{$guildId}/roles"))
            ->filter(fn ($role) => $needle === '' || str_contains(mb_strtolower((string) data_get($role, 'name')), $needle))
            ->take(10)
            ->map(fn ($role) => [
                'id' => (string) data_get($role, 'id'),
                'label' => '@'.data_get($role, 'name'),
                'type' => 'role',
            ]);

        $members = $query === '' ? collect() : collect(
            $this->getList("{$this->baseUrl()}/guilds/{$guildId}/members/search", ['query' => $query, 'limit' => 10])
        )->map(fn ($member) => [
            'id' => (string) data_get($member, 'user.id'),
            'label' => '@'.(data_get($member, 'user.global_name') ?: data_get($member, 'user.username')),
            'type' => 'user',
        ]);

        return $specials->concat($roles)->concat($members)->values()->all();
    }

    /**
     * GETs a Discord list endpoint, returning [] on failure or a non-list body
     * (a failed call returns an error OBJECT, which must not be iterated as rows).
     *
     * @param  array<string, mixed>  $query
     * @return array<int, mixed>
     */
    private function getList(string $url, array $query = []): array
    {
        $body = $this->bot()->get($url, $query)->json();

        return is_array($body) && array_is_list($body) ? $body : [];
    }

    public function getGuild(string $guildId): Response
    {
        return $this->bot()->get("{$this->baseUrl()}/guilds/{$guildId}");
    }

    private function bot(): PendingRequest
    {
        return $this->socialHttp()
            ->withToken((string) config('trypost.platforms.discord.bot_token'), 'Bot');
    }
}
