<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\Automation;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->user = User::factory()->create([
        'current_workspace_id' => $this->workspace->id,
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Admin->value]);
    $this->user->refresh();
});

it('rejects saving a webhook node whose payload template is not valid JSON', function () {
    $automation = Automation::factory()->for($this->workspace)->create();

    $this->actingAs($this->user)
        ->putJson(route('app.automations.update', $automation->id), [
            'nodes' => [
                ['id' => 'n1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['trigger_type' => 'schedule', 'cron' => '0 9 * * *']],
                ['id' => 'n2', 'type' => 'webhook', 'position' => ['x' => 1, 'y' => 0], 'data' => [
                    'url' => 'https://example.test/hook',
                    'method' => 'POST',
                    'payload_template' => '{"title": {{fetched.title}}}',
                ]],
            ],
            'connections' => [['id' => 'e1', 'source' => 'n1', 'target' => 'n2']],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['nodes.1.data.payload_template']);
});

it('allows saving a webhook node whose placeholders are quoted valid JSON', function () {
    $automation = Automation::factory()->for($this->workspace)->create();

    $this->actingAs($this->user)
        ->put(route('app.automations.update', $automation->id), [
            'nodes' => [
                ['id' => 'n1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['trigger_type' => 'schedule', 'cron' => '0 9 * * *']],
                ['id' => 'n2', 'type' => 'webhook', 'position' => ['x' => 1, 'y' => 0], 'data' => [
                    'url' => 'https://example.test/hook',
                    'method' => 'POST',
                    'payload_template' => '{"title": "{{fetched.title}}"}',
                ]],
            ],
            'connections' => [['id' => 'e1', 'source' => 'n1', 'target' => 'n2']],
        ])
        ->assertSessionHasNoErrors();

    expect($automation->fresh()->nodes)->toHaveCount(2);
});

it('allows saving a webhook node with an empty payload template', function () {
    $automation = Automation::factory()->for($this->workspace)->create();

    $this->actingAs($this->user)
        ->put(route('app.automations.update', $automation->id), [
            'nodes' => [
                ['id' => 'n1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['trigger_type' => 'schedule', 'cron' => '0 9 * * *']],
                ['id' => 'n2', 'type' => 'webhook', 'position' => ['x' => 1, 'y' => 0], 'data' => [
                    'url' => 'https://example.test/hook',
                    'method' => 'POST',
                    'payload_template' => '',
                ]],
            ],
            'connections' => [['id' => 'e1', 'source' => 'n1', 'target' => 'n2']],
        ])
        ->assertSessionHasNoErrors();
});

it('refuses to activate an automation whose webhook payload is invalid JSON', function () {
    $automation = Automation::factory()->for($this->workspace)->withScheduleTrigger()->create();
    $automation->update([
        'nodes' => array_merge($automation->nodes, [
            ['id' => 'n2', 'type' => 'webhook', 'position' => ['x' => 1, 'y' => 1], 'data' => [
                'url' => 'https://example.test/hook',
                'payload_template' => '{"title": {{fetched.title}}}',
            ]],
        ]),
        'connections' => [['id' => 'e1', 'source' => 'trigger_1', 'target' => 'n2']],
    ]);

    $this->actingAs($this->user)
        ->postJson(route('app.automations.activate', $automation->id))
        ->assertStatus(422);

    expect($automation->fresh()->status->value)->not->toBe('active');
});

it('refuses to run a test when the webhook payload is invalid JSON', function () {
    $automation = Automation::factory()->for($this->workspace)->withScheduleTrigger()->create();
    $automation->update([
        'nodes' => array_merge($automation->nodes, [
            ['id' => 'n2', 'type' => 'webhook', 'position' => ['x' => 1, 'y' => 1], 'data' => [
                'url' => 'https://example.test/hook',
                'payload_template' => '{"title": {{fetched.title}}}',
            ]],
        ]),
        'connections' => [['id' => 'e1', 'source' => 'trigger_1', 'target' => 'n2']],
    ]);

    $this->actingAs($this->user)
        ->postJson(route('app.automations.test', $automation->id), [])
        ->assertStatus(422);
});
