<?php

declare(strict_types=1);

use App\Actions\Automation\Node\RunEndNode;
use App\Enums\Automation\NodeRun\Status;
use App\Models\AutomationRun;

it('returns completed with ended_at timestamp', function () {
    $run = AutomationRun::factory()->create();

    $result = app(RunEndNode::class)($run, []);

    expect($result->status)->toBe(Status::Completed);
    expect($result->output)->toHaveKey('end');
    expect($result->output['end'])->toHaveKey('ended_at');
    expect($result->output['end']['reason'])->toBeNull();
});

it('captures reason from config', function () {
    $run = AutomationRun::factory()->create();

    $result = app(RunEndNode::class)($run, ['reason' => 'Filtered out']);

    expect($result->output['end']['reason'])->toBe('Filtered out');
});
