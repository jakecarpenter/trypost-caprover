<?php

declare(strict_types=1);

namespace App\Actions\Automation\Node;

use App\DataTransferObjects\Automation\NodeRunResult;
use App\Enums\Automation\DelayUnit;
use App\Models\AutomationRun;
use InvalidArgumentException;

class RunDelayNode
{
    public function __invoke(AutomationRun $run, array $config): NodeRunResult
    {
        $duration = (int) data_get($config, 'duration', 0);
        $unit = data_get($config, 'unit', DelayUnit::Minutes->value);

        $until = match (DelayUnit::tryFrom((string) $unit)) {
            DelayUnit::Minutes => now()->addMinutes($duration),
            DelayUnit::Hours => now()->addHours($duration),
            DelayUnit::Days => now()->addDays($duration),
            default => throw new InvalidArgumentException("Unknown delay unit: {$unit}"),
        };

        return NodeRunResult::sleep($until);
    }
}
