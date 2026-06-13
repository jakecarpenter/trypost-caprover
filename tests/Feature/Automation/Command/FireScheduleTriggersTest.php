<?php

declare(strict_types=1);

use App\Models\Automation;
use App\Models\AutomationTriggerItem;

it('fires only schedule-trigger automations whose cron matches', function () {
    $scheduleAutomation = Automation::factory()->active()->create([
        'nodes' => [['id' => 't', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0],
            'data' => ['trigger_type' => 'schedule', 'cron' => '* * * * *']]],
    ]);
    $rssAutomation = Automation::factory()->active()->withScheduleTrigger()->create();

    $this->artisan('automation:fire-schedule')->assertSuccessful();

    expect(AutomationTriggerItem::where('automation_id', $scheduleAutomation->id)->count())->toBe(1);
    expect(AutomationTriggerItem::where('automation_id', $rssAutomation->id)->count())->toBe(0);
});

it('ignores automations whose trigger is not a schedule', function () {
    $postAutomation = Automation::factory()->active()->create([
        'nodes' => [['id' => 't', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0],
            'data' => ['trigger_type' => 'post_published']]],
    ]);

    $this->artisan('automation:fire-schedule')->assertSuccessful();

    expect(AutomationTriggerItem::where('automation_id', $postAutomation->id)->count())->toBe(0);
});
