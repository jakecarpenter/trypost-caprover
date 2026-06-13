<?php

declare(strict_types=1);

use App\Enums\Automation\Node\Type as NodeType;
use App\Enums\UserWorkspace\Role;
use App\Models\Automation;
use App\Models\AutomationNodeRun;
use App\Models\AutomationRun;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->user = User::factory()->create(['current_workspace_id' => $this->workspace->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Admin->value]);
    $this->user->refresh();
});

it('aggregates node runs from every fan-out branch under the root run', function () {
    $automation = Automation::factory()->for($this->workspace)->create();

    $root = AutomationRun::factory()->for($automation)->create();
    $sibling = AutomationRun::factory()->for($automation)->create(['root_run_id' => $root->id]);

    AutomationNodeRun::factory()->create([
        'run_id' => $root->id,
        'node_id' => 'fetch_rss_1',
        'node_type' => NodeType::FetchRss,
    ]);
    AutomationNodeRun::factory()->create([
        'run_id' => $sibling->id,
        'node_id' => 'http_1',
        'node_type' => NodeType::HttpRequest,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('app.automations.runs.show', [$automation, $root]));

    $response->assertOk();

    $nodeIds = collect($response->json('node_runs'))->pluck('node_id');
    expect($nodeIds)->toHaveCount(2)
        ->and($nodeIds)->toContain('fetch_rss_1')
        ->and($nodeIds)->toContain('http_1');
});

it('returns the same aggregated family when queried from a sibling run', function () {
    $automation = Automation::factory()->for($this->workspace)->create();

    $root = AutomationRun::factory()->for($automation)->create();
    $sibling = AutomationRun::factory()->for($automation)->create(['root_run_id' => $root->id]);

    AutomationNodeRun::factory()->create(['run_id' => $root->id, 'node_id' => 'a']);
    AutomationNodeRun::factory()->create(['run_id' => $sibling->id, 'node_id' => 'b']);

    $response = $this->actingAs($this->user)
        ->getJson(route('app.automations.runs.show', [$automation, $sibling]));

    $response->assertOk();
    expect($response->json('node_runs'))->toHaveCount(2);
});
