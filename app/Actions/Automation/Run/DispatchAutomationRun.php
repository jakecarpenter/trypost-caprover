<?php

declare(strict_types=1);

namespace App\Actions\Automation\Run;

use App\Enums\Automation\Run\Status;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\AutomationTriggerItem;

class DispatchAutomationRun
{
    public function __construct(private AdvanceAutomationRun $advance) {}

    public function __invoke(Automation $automation, AutomationTriggerItem $triggerItem): AutomationRun
    {
        $targets = $this->triggerTargets($automation);

        $run = AutomationRun::create([
            'automation_id' => $automation->id,
            'trigger_item_id' => $triggerItem->id,
            'status' => Status::Pending,
            'context' => ['trigger' => $triggerItem->payload],
        ]);

        if ($targets === []) {
            $run->update([
                'status' => Status::Failed,
                'error' => ['message' => __('automations.errors.no_trigger_connection')],
                'finished_at' => now(),
            ]);

            return $run;
        }

        $this->advance->dispatchBranches($run, $targets);

        return $run;
    }

    /**
     * @return array<int, string>
     */
    private function triggerTargets(Automation $automation): array
    {
        $triggerNode = collect($automation->nodes ?? [])->firstWhere('type', 'trigger');

        if ($triggerNode === null) {
            return [];
        }

        return $this->advance->targetsFor($automation, $triggerNode['id']);
    }
}
