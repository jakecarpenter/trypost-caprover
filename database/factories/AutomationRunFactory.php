<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Automation\Run\Status;
use App\Models\Automation;
use App\Models\AutomationRun;
use Illuminate\Database\Eloquent\Factories\Factory;

class AutomationRunFactory extends Factory
{
    protected $model = AutomationRun::class;

    public function definition(): array
    {
        return [
            'automation_id' => Automation::factory(),
            'status' => Status::Pending,
            'is_manual' => false,
            'is_dry_run' => false,
            'context' => [],
        ];
    }

    public function running(string $nodeId = 'node_1'): static
    {
        return $this->state(fn () => [
            'status' => Status::Running,
            'current_node_id' => $nodeId,
            'started_at' => now(),
        ]);
    }

    public function waiting(\DateTimeInterface $until): static
    {
        return $this->state(fn () => [
            'status' => Status::Waiting,
            'next_action_at' => $until,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => Status::Completed,
            'finished_at' => now(),
        ]);
    }
}
