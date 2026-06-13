<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\AutomationRunUpdated;
use App\Models\AutomationNodeRun;

class AutomationNodeRunObserver
{
    public function created(AutomationNodeRun $nodeRun): void
    {
        AutomationRunUpdated::dispatch($nodeRun->run);
    }

    public function updated(AutomationNodeRun $nodeRun): void
    {
        if ($nodeRun->wasChanged(['status', 'output', 'error', 'finished_at'])) {
            AutomationRunUpdated::dispatch($nodeRun->run);
        }
    }
}
