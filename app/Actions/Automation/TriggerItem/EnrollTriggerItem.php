<?php

declare(strict_types=1);

namespace App\Actions\Automation\TriggerItem;

use App\Actions\Automation\Run\DispatchAutomationRun;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\AutomationTriggerItem;

class EnrollTriggerItem
{
    public function __construct(private DispatchAutomationRun $dispatchRun) {}

    public function __invoke(Automation $automation, string $itemKey, array $payload): ?AutomationRun
    {
        $existing = AutomationTriggerItem::where('automation_id', $automation->id)
            ->where('item_key', $itemKey)
            ->first();

        if ($existing !== null) {
            return null;
        }

        $item = AutomationTriggerItem::create([
            'automation_id' => $automation->id,
            'item_key' => $itemKey,
            'payload' => $payload,
            'first_seen_at' => now(),
        ]);

        return ($this->dispatchRun)($automation, $item);
    }
}
