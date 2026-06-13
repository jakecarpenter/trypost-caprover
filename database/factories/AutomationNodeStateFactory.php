<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Automation;
use App\Models\AutomationNodeState;
use Illuminate\Database\Eloquent\Factories\Factory;

class AutomationNodeStateFactory extends Factory
{
    protected $model = AutomationNodeState::class;

    public function definition(): array
    {
        return [
            'automation_id' => Automation::factory(),
            'node_id' => 'node_'.fake()->randomNumber(6),
            'data' => [],
        ];
    }
}
