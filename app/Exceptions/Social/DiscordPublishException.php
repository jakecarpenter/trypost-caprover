<?php

declare(strict_types=1);

namespace App\Exceptions\Social;

use Illuminate\Http\Client\Response;

class DiscordPublishException extends SocialPublishException
{
    public static function fromApiResponse(mixed $response): static
    {
        /** @var Response $response */
        $status = $response->status();
        $rawResponse = $response->body();
        $code = (int) data_get($response->json(), 'code');
        $message = (string) data_get($response->json(), 'message', 'An unknown Discord error occurred.');

        // Missing access / missing permissions: the bot can't see or post in the channel.
        if (in_array($code, [50001, 50013], true) || $status === 403) {
            return new static(
                userMessage: "The bot can't post in this channel. Make sure it has access and permission to send messages there.",
                category: ErrorCategory::Permission,
                platformErrorCode: (string) $code,
                rawResponse: $rawResponse,
            );
        }

        // Unknown channel — it was deleted or the id is stale.
        if ($code === 10003) {
            return new static(
                userMessage: 'That Discord channel no longer exists. Pick a different channel.',
                category: ErrorCategory::Permission,
                platformErrorCode: (string) $code,
                rawResponse: $rawResponse,
            );
        }

        // Attachment too large.
        if ($code === 40005 || $status === 413) {
            return new static(
                userMessage: "A media file exceeds Discord's upload size limit.",
                category: ErrorCategory::MediaFormat,
                platformErrorCode: (string) $code,
                rawResponse: $rawResponse,
            );
        }

        // 401: the configured bot token is invalid (operator-level misconfiguration).
        if ($status === 401) {
            return new static(
                userMessage: 'Discord rejected the bot token. Check the DISCORD_BOT_TOKEN configuration.',
                category: ErrorCategory::Permission,
                platformErrorCode: (string) $status,
                rawResponse: $rawResponse,
            );
        }

        if ($status === 429) {
            return new static(
                userMessage: 'Discord rate limit reached. Please try again shortly.',
                category: ErrorCategory::RateLimit,
                platformErrorCode: (string) $status,
                rawResponse: $rawResponse,
            );
        }

        if ($status >= 500) {
            return new static(
                userMessage: 'Discord is temporarily unavailable. Please try again later.',
                category: ErrorCategory::ServerError,
                platformErrorCode: (string) $status,
                rawResponse: $rawResponse,
            );
        }

        return new static(
            userMessage: $message,
            category: ErrorCategory::Unknown,
            platformErrorCode: (string) $code,
            rawResponse: $rawResponse,
        );
    }

    public function platform(): string
    {
        return 'discord';
    }
}
