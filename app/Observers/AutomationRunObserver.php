<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\AutomationRunUpdated;
use App\Models\AutomationRun;

class AutomationRunObserver
{
    public function created(AutomationRun $run): void
    {
        AutomationRunUpdated::dispatch($run);
    }

    public function updated(AutomationRun $run): void
    {
        // Only broadcast when the user-visible state changes — skip noisy
        // updates like `context` mutations from intermediate node merges.
        if ($run->wasChanged(['status', 'current_node_id', 'finished_at', 'next_action_at', 'error'])) {
            AutomationRunUpdated::dispatch($run);
        }
    }
}
