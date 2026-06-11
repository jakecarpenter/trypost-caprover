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

it('creates an automation with the default name (web sends no name)', function () {
    $response = $this->actingAs($this->user)
        ->post(route('app.automations.store'));

    $response->assertRedirect();

    $automation = Automation::where('workspace_id', $this->workspace->id)->sole();
    expect($automation->name)->toBe(__('automations.default_name'));
});

it('seeds exactly one schedule trigger when creating an automation', function () {
    $this->actingAs($this->user)
        ->post(route('app.automations.store'))
        ->assertRedirect();

    $automation = Automation::where('workspace_id', $this->workspace->id)->sole();

    expect($automation->nodes)->toHaveCount(1);
    expect($automation->nodes[0]['type'])->toBe('trigger');
    expect($automation->nodes[0]['data']['trigger_type'])->toBe('schedule');
});

it('updates nodes and connections via PUT', function () {
    $automation = Automation::factory()->for($this->workspace)->create();

    $payload = [
        'nodes' => [
            ['id' => 'n1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['trigger_type' => 'schedule', 'cron' => '0 9 * * *']],
            ['id' => 'n2', 'type' => 'fetch_rss', 'position' => ['x' => 200, 'y' => 0], 'data' => ['feed_url' => 'https://example.com/feed.xml']],
            ['id' => 'n3', 'type' => 'generate', 'position' => ['x' => 400, 'y' => 0], 'data' => ['accounts' => [['social_account_id' => 'acc-1']], 'prompt_template' => 'hi', 'image_source' => 'none']],
        ],
        'connections' => [
            ['id' => 'e1', 'source' => 'n1', 'target' => 'n2'],
            ['id' => 'e2', 'source' => 'n2', 'target' => 'n3'],
        ],
    ];

    $this->actingAs($this->user)
        ->put(route('app.automations.update', $automation->id), $payload)
        ->assertRedirect();

    expect($automation->fresh()->nodes)->toHaveCount(3);
});

it('rejects update with cycle', function () {
    $automation = Automation::factory()->for($this->workspace)->create();

    $this->actingAs($this->user)
        ->putJson(route('app.automations.update', $automation->id), [
            'nodes' => [
                ['id' => 'n1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['trigger_type' => 'schedule', 'cron' => '0 9 * * *']],
                ['id' => 'n2', 'type' => 'generate', 'position' => ['x' => 0, 'y' => 0], 'data' => ['accounts' => [['social_account_id' => 'acc-1']], 'prompt_template' => 'hi', 'image_source' => 'none']],
            ],
            'connections' => [
                ['id' => 'e1', 'source' => 'n1', 'target' => 'n2'],
                ['id' => 'e2', 'source' => 'n2', 'target' => 'n1'],
            ],
        ])
        ->assertStatus(422);
});

it('rejects update when an http_request node is missing the url', function () {
    $automation = Automation::factory()->for($this->workspace)->create();

    $this->actingAs($this->user)
        ->putJson(route('app.automations.update', $automation->id), [
            'nodes' => [
                ['id' => 'n1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['trigger_type' => 'schedule', 'cron' => '0 9 * * *']],
                ['id' => 'n2', 'type' => 'http_request', 'position' => ['x' => 200, 'y' => 0], 'data' => ['method' => 'GET', 'auth_type' => 'none']],
            ],
            'connections' => [['id' => 'e1', 'source' => 'n1', 'target' => 'n2']],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['nodes.1.data.url']);
});

it('activates a valid automation', function () {
    $automation = Automation::factory()->for($this->workspace)->withScheduleTrigger()->create();
    $automation->update([
        'nodes' => array_merge($automation->nodes, [
            ['id' => 'n2', 'type' => 'generate', 'position' => ['x' => 1, 'y' => 1], 'data' => []],
        ]),
        'connections' => [['id' => 'e1', 'source' => 'trigger_1', 'target' => 'n2']],
    ]);

    $this->actingAs($this->user)
        ->post(route('app.automations.activate', $automation->id))
        ->assertRedirect();

    expect($automation->fresh()->status->value)->toBe('active');
});

it('refuses activate when trigger has no outgoing connection', function () {
    $automation = Automation::factory()->for($this->workspace)->withScheduleTrigger()->create();

    $this->actingAs($this->user)
        ->postJson(route('app.automations.activate', $automation->id))
        ->assertStatus(422);
});

it('forbids access to automations from another workspace', function () {
    $otherWorkspace = Workspace::factory()->create();
    $otherAutomation = Automation::factory()->for($otherWorkspace)->create();

    $this->actingAs($this->user)
        ->get(route('app.automations.show', $otherAutomation->id))
        ->assertForbidden();

    $this->actingAs($this->user)
        ->get(route('app.automations.edit', $otherAutomation->id))
        ->assertForbidden();

    $this->actingAs($this->user)
        ->putJson(route('app.automations.update', $otherAutomation->id), ['name' => 'Hacked'])
        ->assertForbidden();

    $this->actingAs($this->user)
        ->deleteJson(route('app.automations.destroy', $otherAutomation->id))
        ->assertForbidden();

    $this->actingAs($this->user)
        ->postJson(route('app.automations.activate', $otherAutomation->id))
        ->assertForbidden();

    $this->actingAs($this->user)
        ->postJson(route('app.automations.pause', $otherAutomation->id))
        ->assertForbidden();
});
