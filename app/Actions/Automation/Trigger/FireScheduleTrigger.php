<?php

declare(strict_types=1);

namespace App\Actions\Automation\Trigger;

use App\Actions\Automation\TriggerItem\EnrollTriggerItem;
use App\Models\Automation;
use Cron\CronExpression;

class FireScheduleTrigger
{
    public function __construct(private EnrollTriggerItem $enroll) {}

    public function __invoke(Automation $automation): bool
    {
        $triggerNode = collect($automation->nodes ?? [])->firstWhere('type', 'trigger');
        $cron = data_get($triggerNode, 'data.cron');
        $timezone = data_get($triggerNode, 'data.schedule_timezone', config('app.timezone'));

        if ($cron === null) {
            return false;
        }

        $expression = new CronExpression($cron);

        if (! $expression->isDue(now(), $timezone)) {
            return false;
        }

        $key = now()->format('Y-m-d\TH:i');
        $payload = ['fired_at' => now()->toIso8601String()];

        return ($this->enroll)($automation, $key, $payload) !== null;
    }
}
