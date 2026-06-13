<?php

declare(strict_types=1);

use App\Enums\Automation\Run\Status;
use App\Jobs\Automation\ProcessAutomationNode;
use App\Models\Automation;
use App\Models\AutomationRun;
use Illuminate\Support\Facades\Queue;

it('wakes runs whose next_action_at is in the past', function () {
    Queue::fake();

    $automation = Automation::factory()->active()->create([
        'nodes' => [
            ['id' => 't', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
            ['id' => 'g', 'type' => 'generate', 'position' => ['x' => 1, 'y' => 0], 'data' => []],
        ],
        'connections' => [['id' => 'e1', 'source' => 't', 'target' => 'g']],
    ]);

    $run = AutomationRun::factory()->for($automation)->create([
        'status' => Status::Waiting,
        'current_node_id' => 't',
        'next_action_at' => now()->subMinutes(5),
    ]);

    $this->artisan('automation:process-delays')->assertSuccessful();

    $fresh = $run->fresh();
    expect($fresh->status)->toBe(Status::Running);
    expect($fresh->next_action_at)->toBeNull();

    Queue::assertPushed(ProcessAutomationNode::class);
});

it('claims each run once — a second tick does not re-dispatch an already-claimed run', function () {
    Queue::fake();

    $automation = Automation::factory()->active()->create([
        'nodes' => [
            ['id' => 't', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
            ['id' => 'g', 'type' => 'generate', 'position' => ['x' => 1, 'y' => 0], 'data' => []],
        ],
        'connections' => [['id' => 'e1', 'source' => 't', 'target' => 'g']],
    ]);

    $run = AutomationRun::factory()->for($automation)->create([
        'status' => Status::Waiting,
        'current_node_id' => 't',
        'next_action_at' => now()->subMinutes(5),
    ]);

    $this->artisan('automation:process-delays')->assertSuccessful();
    $this->artisan('automation:process-delays')->assertSuccessful();

    Queue::assertPushed(ProcessAutomationNode::class, 1);
    expect($run->fresh()->status)->toBe(Status::Running);
});

it('does not wake runs whose next_action_at is in the future', function () {
    Queue::fake();

    $automation = Automation::factory()->create();
    $run = AutomationRun::factory()->for($automation)->create([
        'status' => Status::Waiting,
        'next_action_at' => now()->addMinutes(5),
    ]);

    $this->artisan('automation:process-delays')->assertSuccessful();

    expect($run->fresh()->status)->toBe(Status::Waiting);
    Queue::assertNotPushed(ProcessAutomationNode::class);
});
