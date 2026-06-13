<?php

declare(strict_types=1);

use App\Actions\Automation\Run\AdvanceAutomationRun;
use App\Enums\Automation\Run\Status as RunStatus;
use App\Models\Automation;
use App\Models\AutomationRun;

it('completes with a reason when no edge matches the handle', function () {
    $automation = Automation::factory()->create([
        'nodes' => [
            ['id' => 'a', 'type' => 'fetch_rss', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
        ],
        'connections' => [],
    ]);
    $run = AutomationRun::factory()->for($automation)->running('a')->create();

    app(AdvanceAutomationRun::class)($run, 'a', 'no_items');

    $run->refresh();
    expect($run->status)->toBe(RunStatus::Completed);
    expect(data_get($run->error, 'reason'))->toBe('no_matching_edge');
    expect(data_get($run->error, 'handle'))->toBe('no_items');
});
