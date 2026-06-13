<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Automation\Node\Type as NodeType;
use App\Enums\Automation\NodeRun\Status;
use App\Models\AutomationNodeRun;
use App\Models\AutomationRun;
use Illuminate\Database\Eloquent\Factories\Factory;

class AutomationNodeRunFactory extends Factory
{
    protected $model = AutomationNodeRun::class;

    public function definition(): array
    {
        return [
            'run_id' => AutomationRun::factory(),
            'node_id' => 'node_'.fake()->randomNumber(6),
            'node_type' => NodeType::Generate,
            'status' => Status::Running,
            'input' => [],
            'started_at' => now(),
        ];
    }
}
