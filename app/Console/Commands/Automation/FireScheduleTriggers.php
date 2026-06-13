<?php

declare(strict_types=1);

namespace App\Console\Commands\Automation;

use App\Actions\Automation\Trigger\FireScheduleTrigger;
use App\Enums\Automation\Status;
use App\Enums\Automation\Trigger\Type as TriggerType;
use App\Models\Automation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('automation:fire-schedule')]
#[Description('Fire scheduled automations whose cron matches now')]
class FireScheduleTriggers extends Command
{
    public function handle(FireScheduleTrigger $fire): int
    {
        Automation::query()
            ->where('status', Status::Active)
            ->where('trigger_type', TriggerType::Schedule->value)
            ->chunkById(50, function ($automations) use ($fire) {
                foreach ($automations as $automation) {
                    $fire($automation);
                }
            });

        return self::SUCCESS;
    }
}
