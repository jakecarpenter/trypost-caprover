<?php

declare(strict_types=1);

use App\Enums\Automation\Run\Status as RunStatus;
use App\Jobs\Automation\ProcessAutomationNode;
use App\Models\Automation;
use App\Models\AutomationRun;

it('marks the run failed when the job fails', function () {
    $automation = Automation::factory()->active()->create([
        'nodes' => [['id' => 'a', 'type' => 'generate', 'position' => ['x' => 0, 'y' => 0], 'data' => []]],
        'connections' => [],
    ]);
    $run = AutomationRun::factory()->for($automation)->create(['status' => RunStatus::Running, 'current_node_id' => 'a']);

    (new ProcessAutomationNode($run, 'a'))->failed(new RuntimeException('boom'));

    $run->refresh();
    expect($run->status)->toBe(RunStatus::Failed);
    expect(data_get($run->error, 'message'))->toBe('boom');
    expect($run->finished_at)->not->toBeNull();
});

it('does not override an already-terminal run', function () {
    $automation = Automation::factory()->active()->create(['nodes' => [], 'connections' => []]);
    $run = AutomationRun::factory()->for($automation)->create(['status' => RunStatus::Completed]);

    (new ProcessAutomationNode($run, 'a'))->failed(new RuntimeException('late'));

    expect($run->fresh()->status)->toBe(RunStatus::Completed);
});
