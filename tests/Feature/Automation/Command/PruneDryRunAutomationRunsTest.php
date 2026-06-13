<?php

declare(strict_types=1);

use App\Models\AutomationNodeRun;
use App\Models\AutomationRun;

it('prunes terminal dry-run rows older than the grace window', function () {
    $old = AutomationRun::factory()->create(['is_dry_run' => true, 'finished_at' => now()->subHour()]);
    $recent = AutomationRun::factory()->create(['is_dry_run' => true, 'finished_at' => now()->subMinute()]);
    $realOld = AutomationRun::factory()->create(['is_dry_run' => false, 'finished_at' => now()->subHour()]);

    $this->artisan('automation:prune-dry-runs')->assertExitCode(0);

    expect(AutomationRun::find($old->id))->toBeNull();
    expect(AutomationRun::find($recent->id))->not->toBeNull();
    expect(AutomationRun::find($realOld->id))->not->toBeNull();
});

it('leaves dry-run rows that have not finished yet', function () {
    $unfinished = AutomationRun::factory()->create(['is_dry_run' => true, 'finished_at' => null, 'updated_at' => now()->subHour()]);

    $this->artisan('automation:prune-dry-runs')->assertExitCode(0);

    expect(AutomationRun::find($unfinished->id))->not->toBeNull();
});

it('deletes the node runs belonging to pruned dry runs', function () {
    $old = AutomationRun::factory()->create(['is_dry_run' => true, 'finished_at' => now()->subHour()]);
    $nodeRun = AutomationNodeRun::factory()->create(['run_id' => $old->id]);

    $this->artisan('automation:prune-dry-runs')->assertExitCode(0);

    expect(AutomationRun::find($old->id))->toBeNull();
    expect(AutomationNodeRun::find($nodeRun->id))->toBeNull();
});
