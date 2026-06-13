<?php

declare(strict_types=1);

use App\Actions\Automation\Trigger\FireScheduleTrigger;
use App\Models\Automation;
use App\Models\AutomationTriggerItem;
use Carbon\Carbon;

it('fires a trigger when cron matches current minute', function () {
    $automation = Automation::factory()->active()->create([
        'nodes' => [[
            'id' => 'trigger_1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0],
            'data' => ['trigger_type' => 'schedule', 'cron' => '* * * * *'],
        ]],
        'connections' => [],
    ]);

    app(FireScheduleTrigger::class)($automation);

    expect(AutomationTriggerItem::where('automation_id', $automation->id)->count())->toBe(1);
});

it('does not fire when cron does not match', function () {
    $automation = Automation::factory()->active()->create([
        'nodes' => [[
            'id' => 'trigger_1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0],
            'data' => ['trigger_type' => 'schedule', 'cron' => '0 0 1 1 *'],
        ]],
        'connections' => [],
    ]);

    Carbon::setTestNow('2026-05-22 09:00:00');

    app(FireScheduleTrigger::class)($automation);

    expect(AutomationTriggerItem::count())->toBe(0);
});
