<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Automation\Status;
use App\Models\Automation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class AutomationFactory extends Factory
{
    protected $model = Automation::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'name' => fake()->sentence(3),
            'status' => Status::Draft,
            'nodes' => [],
            'connections' => [],
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => Status::Active,
            'activated_at' => now(),
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn () => [
            'status' => Status::Paused,
            'paused_at' => now(),
        ]);
    }

    public function withScheduleTrigger(string $cron = '0 9 * * *'): static
    {
        return $this->state(fn () => [
            'nodes' => [
                [
                    'id' => 'trigger_1',
                    'type' => 'trigger',
                    'position' => ['x' => 0, 'y' => 0],
                    'data' => [
                        'trigger_type' => 'schedule',
                        'cron' => $cron,
                    ],
                ],
            ],
            'connections' => [],
        ]);
    }
}
