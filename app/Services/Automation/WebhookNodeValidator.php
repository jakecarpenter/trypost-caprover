<?php

declare(strict_types=1);

namespace App\Services\Automation;

/**
 * Backend mirror of the Webhook node's payload-template contract: the template
 * is parsed as JSON before placeholders are resolved (see RunWebhookNode), so a
 * template with unquoted `{{ }}` placeholders or any malformed JSON can never
 * run. An empty or literal-`null` template means "no body" and is valid.
 */
final class WebhookNodeValidator
{
    /**
     * First compliance issue for a webhook node's config, or null when valid.
     *
     * @param  array<string, mixed>  $config
     */
    public function issueFor(array $config): ?string
    {
        $template = trim((string) data_get($config, 'payload_template', ''));

        if ($template === '' || $template === 'null') {
            return null;
        }

        json_decode($template);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return __('automations.errors.webhook_invalid_payload_json');
        }

        return null;
    }
}
