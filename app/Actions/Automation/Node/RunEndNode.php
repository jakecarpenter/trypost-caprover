<?php

declare(strict_types=1);

namespace App\Actions\Automation\Node;

use App\DataTransferObjects\Automation\NodeRunResult;
use App\Models\AutomationRun;

class RunEndNode
{
    public function __invoke(AutomationRun $run, array $config): NodeRunResult
    {
        $reason = data_get($config, 'reason');

        return NodeRunResult::completed(output: [
            'end' => [
                'ended_at' => now()->toIso8601String(),
                'reason' => $reason ?: null,
            ],
        ]);
    }
}
