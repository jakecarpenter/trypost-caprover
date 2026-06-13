<?php

declare(strict_types=1);

namespace App\Actions\Automation\Run;

use App\Enums\Automation\Run\Status;
use App\Jobs\Automation\ProcessAutomationNode;
use App\Models\AutomationRun;
use DomainException;

class RetryRunFromNode
{
    public function __invoke(AutomationRun $run, string $nodeId): void
    {
        if ($run->status !== Status::Failed) {
            throw new DomainException(__('automations.errors.only_failed_can_retry'));
        }

        $run->update([
            'status' => Status::Pending,
            'error' => null,
            'finished_at' => null,
        ]);

        ProcessAutomationNode::dispatch($run, $nodeId);
    }
}
