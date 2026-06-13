<?php

declare(strict_types=1);

use App\Enums\Automation\Run\Status as RunStatus;
use App\Models\AutomationRun;

it('fails runs stuck running past the threshold', function () {
    $stuck = AutomationRun::factory()->create(['status' => RunStatus::Running, 'started_at' => now()->subHours(3), 'updated_at' => now()->subHours(3)]);

    $this->artisan('automation:recover-stuck-runs')->assertExitCode(0);

    $stuck->refresh();
    expect($stuck->status)->toBe(RunStatus::Failed);
    expect(data_get($stuck->error, 'reason'))->toBe('stuck');
    expect($stuck->finished_at)->not->toBeNull();
});

it('fails runs stuck pending past the threshold', function () {
    $stuck = AutomationRun::factory()->create(['status' => RunStatus::Pending, 'updated_at' => now()->subHours(3)]);

    $this->artisan('automation:recover-stuck-runs')->assertExitCode(0);

    $stuck->refresh();
    expect($stuck->status)->toBe(RunStatus::Failed);
    expect(data_get($stuck->error, 'reason'))->toBe('stuck');
    expect($stuck->finished_at)->not->toBeNull();
});

it('leaves recent running runs alone', function () {
    $recent = AutomationRun::factory()->create(['status' => RunStatus::Running, 'started_at' => now()->subMinute(), 'updated_at' => now()->subMinute()]);

    $this->artisan('automation:recover-stuck-runs');

    expect($recent->fresh()->status)->toBe(RunStatus::Running);
});

it('does not touch waiting runs', function () {
    $waiting = AutomationRun::factory()->create(['status' => RunStatus::Waiting, 'updated_at' => now()->subDay(), 'next_action_at' => now()->addDay()]);

    $this->artisan('automation:recover-stuck-runs');

    expect($waiting->fresh()->status)->toBe(RunStatus::Waiting);
});
