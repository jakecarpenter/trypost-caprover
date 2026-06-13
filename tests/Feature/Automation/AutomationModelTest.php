<?php

declare(strict_types=1);

use App\Enums\Automation\Status;
use App\Models\Automation;
use App\Models\AutomationNodeRun;
use App\Models\AutomationRun;
use App\Models\AutomationTriggerItem;
use Illuminate\Database\UniqueConstraintViolationException;

it('persists automation with json columns and enum cast', function () {
    $automation = Automation::factory()->withScheduleTrigger()->create();

    expect($automation->status)->toBe(Status::Draft);
    expect($automation->nodes)->toBeArray();
    expect($automation->nodes[0]['type'])->toBe('trigger');
});

it('derives the trigger_type column from the trigger node on save', function () {
    $automation = Automation::factory()->withScheduleTrigger()->create();

    expect($automation->trigger_type)->toBe('schedule');

    $automation->update([
        'nodes' => [
            ['id' => 'trigger_1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0],
                'data' => ['trigger_type' => 'post_published']],
        ],
    ]);

    expect($automation->fresh()->trigger_type)->toBe('post_published');
});

it('nulls the trigger_type column when there is no trigger node', function () {
    $automation = Automation::factory()->create(['nodes' => []]);

    expect($automation->trigger_type)->toBeNull();
});

it('computes run duration only once both timestamps are set', function () {
    $start = now()->startOfSecond();

    $running = AutomationRun::factory()->make(['started_at' => $start, 'finished_at' => null]);
    $finished = AutomationRun::factory()->make([
        'started_at' => $start,
        'finished_at' => $start->copy()->addSeconds(2),
    ]);

    expect($running->durationInMilliseconds())->toBeNull()
        ->and($finished->durationInMilliseconds())->toBe(2000);
});

it('relates trigger items, runs and node runs', function () {
    $automation = Automation::factory()->create();
    $item = AutomationTriggerItem::factory()->for($automation)->create();
    $run = AutomationRun::factory()->for($automation)->create(['trigger_item_id' => $item->id]);
    $nodeRun = AutomationNodeRun::factory()->for($run, 'run')->create();

    expect($automation->triggerItems)->toHaveCount(1);
    expect($automation->runs)->toHaveCount(1);
    expect($run->triggerItem->id)->toBe($item->id);
    expect($run->nodeRuns)->toHaveCount(1);
    expect($nodeRun->run->id)->toBe($run->id);
});

it('enforces unique item_key per automation', function () {
    $automation = Automation::factory()->create();
    AutomationTriggerItem::factory()->for($automation)->create(['item_key' => 'abc']);

    expect(fn () => AutomationTriggerItem::factory()->for($automation)->create(['item_key' => 'abc']))
        ->toThrow(UniqueConstraintViolationException::class);
});
