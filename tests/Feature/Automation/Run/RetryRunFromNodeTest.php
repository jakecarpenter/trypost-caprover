<?php

declare(strict_types=1);

use App\Actions\Automation\Run\RetryRunFromNode;
use App\Enums\Automation\Run\Status;
use App\Jobs\Automation\ProcessAutomationNode;
use App\Models\Automation;
use App\Models\AutomationRun;
use Illuminate\Support\Facades\Queue;

it('resets a failed run to pending and dispatches the next node', function () {
    Queue::fake();

    $automation = Automation::factory()->create();
    $run = AutomationRun::factory()->for($automation)->create([
        'status' => Status::Failed,
        'error' => ['message' => 'previous failure'],
        'finished_at' => now(),
        'current_node_id' => 'g',
    ]);

    app(RetryRunFromNode::class)($run, 'g');

    $fresh = $run->fresh();
    expect($fresh->status)->toBe(Status::Pending);
    expect($fresh->error)->toBeNull();
    expect($fresh->finished_at)->toBeNull();

    Queue::assertPushed(ProcessAutomationNode::class);
});

it('rejects retry for runs that are not failed', function () {
    $automation = Automation::factory()->create();
    $run = AutomationRun::factory()->for($automation)->create(['status' => Status::Completed]);

    expect(fn () => app(RetryRunFromNode::class)($run, 'g'))
        ->toThrow(DomainException::class);
});
