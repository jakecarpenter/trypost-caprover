<?php

declare(strict_types=1);

namespace App\Console\Commands\Automation;

use App\Enums\Automation\Run\Status;
use App\Models\AutomationRun;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('automation:recover-stuck-runs')]
#[Description('Fail automation runs stuck running or pending for more than 1 hour')]
class RecoverStuckAutomationRuns extends Command
{
    public function handle(): int
    {
        $count = AutomationRun::query()
            ->whereIn('status', [Status::Running, Status::Pending])
            ->where('updated_at', '<=', now()->subHour())
            ->update([
                'status' => Status::Failed,
                'error' => ['reason' => 'stuck'],
                'finished_at' => now(),
            ]);

        $this->info("Recovered {$count} stuck automation runs.");

        return self::SUCCESS;
    }
}
