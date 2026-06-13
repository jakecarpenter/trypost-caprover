<?php

declare(strict_types=1);

use App\Actions\Automation\Node\RunDelayNode;
use App\Enums\Automation\NodeRun\Status;
use App\Models\AutomationRun;

it('returns sleep_until with duration in hours', function () {
    $run = AutomationRun::factory()->create();
    $handler = app(RunDelayNode::class);

    $result = $handler($run, ['duration' => 2, 'unit' => 'hours']);

    expect($result->status)->toBe(Status::Completed);
    expect($result->sleepUntil)->not->toBeNull();
    expect(now()->diffInMinutes($result->sleepUntil))->toBeGreaterThanOrEqual(119);
});

it('supports minutes and days units', function () {
    $run = AutomationRun::factory()->create();
    $handler = app(RunDelayNode::class);

    expect($handler($run, ['duration' => 30, 'unit' => 'minutes'])->sleepUntil)
        ->not->toBeNull();
    expect(now()->diffInHours($handler($run, ['duration' => 1, 'unit' => 'days'])->sleepUntil))
        ->toBeGreaterThanOrEqual(23);
});

it('throws on an unknown delay unit', function () {
    $run = AutomationRun::factory()->create();

    expect(fn () => app(RunDelayNode::class)($run, ['duration' => 5, 'unit' => 'weeks']))
        ->toThrow(InvalidArgumentException::class);
});
