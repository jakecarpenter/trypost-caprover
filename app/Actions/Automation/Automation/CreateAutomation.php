<?php

declare(strict_types=1);

namespace App\Actions\Automation\Automation;

use App\Enums\Automation\ScheduleField;
use App\Enums\Automation\Status;
use App\Enums\Automation\Trigger\Type as TriggerType;
use App\Models\Automation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

class CreateAutomation
{
    public function __invoke(Workspace $workspace, User $user, ?string $name = null): Automation
    {
        return Automation::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'name' => $name ?: __('automations.default_name'),
            'status' => Status::Draft,
            'nodes' => [$this->defaultTriggerNode()],
            'connections' => [],
        ]);
    }

    /**
     * Every automation has exactly one trigger — its entry point — so we seed it
     * on creation. The trigger can't be added or deleted from the editor; only
     * its type (schedule / post published / post scheduled) is configurable.
     *
     * @return array<string, mixed>
     */
    private function defaultTriggerNode(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'type' => 'trigger',
            'position' => ['x' => 0, 'y' => 0],
            'data' => [
                'trigger_type' => TriggerType::Schedule->value,
                'cron' => '0 9 * * *',
                'schedule_field' => ScheduleField::Days->value,
                'schedule_days_interval' => 1,
                'schedule_hour' => 9,
                'schedule_minute' => 0,
                'schedule_timezone' => config('app.timezone'),
            ],
        ];
    }
}
