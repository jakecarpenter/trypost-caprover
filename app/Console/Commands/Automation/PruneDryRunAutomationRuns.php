<?php

declare(strict_types=1);

namespace App\Console\Commands\Automation;

use App\Models\AutomationRun;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('automation:prune-dry-runs')]
#[Description('Delete terminal dry-run automation runs older than the test-panel grace window')]
class PruneDryRunAutomationRuns extends Command
{
    private const GRACE_MINUTES = 10;

    public function handle(): int
    {
        $count = AutomationRun::query()
            ->where('is_dry_run', true)
            ->whereNotNull('finished_at')
            ->where('finished_at', '<=', now()->subMinutes(self::GRACE_MINUTES))
            ->delete();

        $this->info("Pruned {$count} dry-run automation runs.");

        return self::SUCCESS;
    }
}
