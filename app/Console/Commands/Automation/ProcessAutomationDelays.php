<?php

declare(strict_types=1);

namespace App\Console\Commands\Automation;

use App\Actions\Automation\Run\AdvanceAutomationRun;
use App\Enums\Automation\Run\Status;
use App\Enums\Automation\Status as AutomationStatus;
use App\Models\AutomationRun;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('automation:process-delays')]
#[Description('Wake up runs that finished their delay window')]
class ProcessAutomationDelays extends Command
{
    public function handle(AdvanceAutomationRun $advance): int
    {
        AutomationRun::query()
            ->where('status', Status::Waiting)
            ->where('next_action_at', '<=', now())
            ->where(fn ($query) => $query
                ->where('is_manual', true)
                ->orWhereHas('automation', fn ($inner) => $inner->where('status', AutomationStatus::Active)))
            ->chunkById(50, function ($runs) use ($advance) {
                foreach ($runs as $run) {
                    $claimed = AutomationRun::query()
                        ->whereKey($run->id)
                        ->where('status', Status::Waiting)
                        ->update([
                            'status' => Status::Running,
                            'next_action_at' => null,
                        ]);

                    if ($claimed === 0) {
                        continue;
                    }

                    $run->refresh();
                    $advance($run, $run->current_node_id);
                }
            });

        return self::SUCCESS;
    }
}
