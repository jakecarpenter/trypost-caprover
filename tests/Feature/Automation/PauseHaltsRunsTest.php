<?php

declare(strict_types=1);

use App\Actions\Automation\Run\AdvanceAutomationRun;
use App\Enums\Automation\Run\Status as RunStatus;
use App\Jobs\Automation\ProcessAutomationNode;
use App\Models\Automation;
use App\Models\AutomationRun;

it('does not advance a node when the automation is paused', function () {
    $automation = Automation::factory()->paused()->create([
        'nodes' => [['id' => 'a', 'type' => 'end', 'position' => ['x' => 0, 'y' => 0], 'data' => []]],
        'connections' => [],
    ]);
    $run = AutomationRun::factory()->for($automation)->create(['status' => RunStatus::Pending]);

    (new ProcessAutomationNode($run, 'a'))->handle(app(AdvanceAutomationRun::class));

    expect($run->fresh()->status)->toBe(RunStatus::Pending);
});

it('does not resume a waiting run while the automation is paused', function () {
    $automation = Automation::factory()->paused()->create([
        'nodes' => [
            ['id' => 'delay', 'type' => 'delay', 'position' => ['x' => 0, 'y' => 0], 'data' => ['duration' => 1, 'unit' => 'hours']],
            ['id' => 'end', 'type' => 'end', 'position' => ['x' => 1, 'y' => 0], 'data' => []],
        ],
        'connections' => [['id' => 'e1', 'source' => 'delay', 'target' => 'end']],
    ]);
    $run = AutomationRun::factory()->for($automation)->waiting(now()->subMinute())->create(['current_node_id' => 'delay']);

    $this->artisan('automation:process-delays');

    expect($run->fresh()->status)->toBe(RunStatus::Waiting);
});

it('advances normally when the automation is active', function () {
    $automation = Automation::factory()->active()->create([
        'nodes' => [['id' => 'a', 'type' => 'end', 'position' => ['x' => 0, 'y' => 0], 'data' => []]],
        'connections' => [],
    ]);
    $run = AutomationRun::factory()->for($automation)->create(['status' => RunStatus::Pending]);

    (new ProcessAutomationNode($run, 'a'))->handle(app(AdvanceAutomationRun::class));

    expect($run->fresh()->status)->toBe(RunStatus::Completed);
});

it('advances a manual test run even when the automation is not active', function () {
    $automation = Automation::factory()->create([
        'nodes' => [['id' => 'a', 'type' => 'end', 'position' => ['x' => 0, 'y' => 0], 'data' => []]],
        'connections' => [],
    ]);
    $run = AutomationRun::factory()->for($automation)->create(['status' => RunStatus::Pending, 'is_manual' => true]);

    (new ProcessAutomationNode($run, 'a'))->handle(app(AdvanceAutomationRun::class));

    expect($run->fresh()->status)->toBe(RunStatus::Completed);
});

it('resumes a waiting manual test run regardless of automation status', function () {
    $automation = Automation::factory()->paused()->create([
        'nodes' => [
            ['id' => 'delay', 'type' => 'delay', 'position' => ['x' => 0, 'y' => 0], 'data' => ['duration' => 1, 'unit' => 'hours']],
            ['id' => 'end', 'type' => 'end', 'position' => ['x' => 1, 'y' => 0], 'data' => []],
        ],
        'connections' => [['id' => 'e1', 'source' => 'delay', 'target' => 'end']],
    ]);
    $run = AutomationRun::factory()->for($automation)->waiting(now()->subMinute())->create(['current_node_id' => 'delay', 'is_manual' => true]);

    $this->artisan('automation:process-delays');

    expect($run->fresh()->status)->toBe(RunStatus::Completed);
});
