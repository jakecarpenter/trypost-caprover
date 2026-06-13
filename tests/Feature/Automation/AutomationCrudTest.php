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

it('persists every trigger schedule editor field through a save round-trip', function (array $scheduleData) {
    $automation = Automation::factory()->for($this->workspace)->create();

    $this->actingAs($this->user)
        ->put(route('app.automations.update', $automation->id), [
            'nodes' => [
                ['id' => 'n1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => array_merge([
                    'trigger_type' => 'schedule',
                    'cron' => '0 9 * * *',
                ], $scheduleData)],
            ],
            'connections' => [],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $trigger = collect($automation->fresh()->nodes)->firstWhere('id', 'n1');

    foreach ($scheduleData as $key => $value) {
        expect($trigger['data'][$key])->toBe($value);
    }
})->with([
    'minutes interval' => [['schedule_field' => 'minutes', 'schedule_minutes_interval' => 15]],
    'hours interval' => [['schedule_field' => 'hours', 'schedule_hours_interval' => 6, 'schedule_minute' => 30]],
    'daily at time' => [['schedule_field' => 'days', 'schedule_days_interval' => 2, 'schedule_hour' => 8, 'schedule_minute' => 45]],
    'weekly on weekdays' => [['schedule_field' => 'weeks', 'schedule_weekdays' => [1, 3, 5], 'schedule_hour' => 14, 'schedule_minute' => 0]],
    'monthly on day-of-month' => [['schedule_field' => 'months', 'schedule_day_of_month' => 15, 'schedule_hour' => 9, 'schedule_minute' => 0]],
    'timezone' => [['schedule_timezone' => 'America/Sao_Paulo']],
    'all fields together' => [[
        'schedule_field' => 'weeks',
        'schedule_minutes_interval' => 5,
        'schedule_hours_interval' => 3,
        'schedule_days_interval' => 1,
        'schedule_hour' => 14,
        'schedule_minute' => 30,
        'schedule_weekdays' => [0, 6],
        'schedule_day_of_month' => 28,
        'schedule_timezone' => 'Europe/Lisbon',
    ]],
]);

it('rejects a trigger node with an out-of-range or invalid schedule field', function (array $scheduleData, string $invalidKey) {
    $automation = Automation::factory()->for($this->workspace)->create();

    $this->actingAs($this->user)
        ->putJson(route('app.automations.update', $automation->id), [
            'nodes' => [
                ['id' => 'n1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => array_merge([
                    'trigger_type' => 'schedule',
                    'cron' => '0 9 * * *',
                ], $scheduleData)],
            ],
            'connections' => [],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(["nodes.0.data.{$invalidKey}"]);
})->with([
    'bad schedule_field' => [['schedule_field' => 'fortnightly'], 'schedule_field'],
    'hour over 23' => [['schedule_field' => 'days', 'schedule_hour' => 24], 'schedule_hour'],
    'minute over 59' => [['schedule_field' => 'days', 'schedule_minute' => 60], 'schedule_minute'],
    'weekday over 6' => [['schedule_field' => 'weeks', 'schedule_weekdays' => [7]], 'schedule_weekdays.0'],
    'day-of-month over 31' => [['schedule_field' => 'months', 'schedule_day_of_month' => 32], 'schedule_day_of_month'],
    'invalid timezone' => [['schedule_timezone' => 'Mars/Phobos'], 'schedule_timezone'],
]);

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

it('rejects a scheduled publish node with no scheduled_offset', function () {
    $automation = Automation::factory()->for($this->workspace)->create();

    $this->actingAs($this->user)
        ->putJson(route('app.automations.update', $automation->id), [
            'nodes' => [
                ['id' => 'n1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['trigger_type' => 'schedule', 'cron' => '0 9 * * *']],
                ['id' => 'n2', 'type' => 'publish', 'position' => ['x' => 200, 'y' => 0], 'data' => ['mode' => 'scheduled']],
            ],
            'connections' => [['id' => 'e1', 'source' => 'n1', 'target' => 'n2']],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['nodes.1.data.scheduled_offset']);
});

it('allows a now publish node without scheduled_offset', function () {
    $automation = Automation::factory()->for($this->workspace)->create();

    $this->actingAs($this->user)
        ->put(route('app.automations.update', $automation->id), [
            'nodes' => [
                ['id' => 'n1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['trigger_type' => 'schedule', 'cron' => '0 9 * * *']],
                ['id' => 'n2', 'type' => 'publish', 'position' => ['x' => 200, 'y' => 0], 'data' => ['mode' => 'now']],
            ],
            'connections' => [['id' => 'e1', 'source' => 'n1', 'target' => 'n2']],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
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
        ->get(route('app.automations.workflow', $otherAutomation->id))
        ->assertForbidden();

    $this->actingAs($this->user)
        ->get(route('app.automations.invocations', $otherAutomation->id))
        ->assertForbidden();

    $this->actingAs($this->user)
        ->get(route('app.automations.metrics', $otherAutomation->id))
        ->assertForbidden();

    $this->actingAs($this->user)
        ->get(route('app.automations.settings', $otherAutomation->id))
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
