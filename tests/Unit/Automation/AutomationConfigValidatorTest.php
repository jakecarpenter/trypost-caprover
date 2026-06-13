<?php

declare(strict_types=1);

use App\Services\Automation\AutomationConfigValidator;
use App\Services\Automation\WebhookNodeValidator;

it('treats empty and literal-null webhook payloads as valid', function (string $template) {
    $issue = app(WebhookNodeValidator::class)->issueFor(['payload_template' => $template]);

    expect($issue)->toBeNull();
})->with(['', '   ', 'null']);

it('accepts a webhook payload whose placeholders are quoted valid JSON', function () {
    $issue = app(WebhookNodeValidator::class)->issueFor([
        'payload_template' => '{"title": "{{fetched.title}}"}',
    ]);

    expect($issue)->toBeNull();
});

it('rejects a webhook payload that is not valid JSON', function () {
    $issue = app(WebhookNodeValidator::class)->issueFor([
        'payload_template' => '{"title": {{fetched.title}}}',
    ]);

    expect($issue)->toBe(__('automations.errors.webhook_invalid_payload_json'));
});

it('reports an issue per invalid node, keyed to its field and index', function () {
    $nodes = [
        ['type' => 'trigger', 'data' => ['trigger_type' => 'schedule']],
        ['type' => 'webhook', 'data' => ['payload_template' => 'not json']],
        ['type' => 'webhook', 'data' => ['payload_template' => '{"ok": "{{x}}"}']],
        ['type' => 'webhook', 'data' => ['payload_template' => '{bad']],
    ];

    $issues = app(AutomationConfigValidator::class)->issues($nodes);

    expect($issues)->toHaveCount(2)
        ->and($issues[0])->toMatchArray(['node_index' => 1, 'field' => 'payload_template'])
        ->and($issues[1]['node_index'])->toBe(3);
});

it('returns the first issue message and null when every node is valid', function () {
    $validator = app(AutomationConfigValidator::class);

    $invalid = [
        ['type' => 'webhook', 'data' => ['payload_template' => 'nope']],
        ['type' => 'webhook', 'data' => ['payload_template' => 'also nope']],
    ];
    $valid = [['type' => 'webhook', 'data' => ['payload_template' => '{}']]];

    expect($validator->firstMessage($invalid))->toBe(__('automations.errors.webhook_invalid_payload_json'))
        ->and($validator->firstMessage($valid))->toBeNull();
});
