<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Automation;
use App\Models\AutomationTriggerItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class AutomationTriggerItemFactory extends Factory
{
    protected $model = AutomationTriggerItem::class;

    public function definition(): array
    {
        return [
            'automation_id' => Automation::factory(),
            'item_key' => fake()->uuid(),
            'payload' => [
                'title' => fake()->sentence(),
                'url' => fake()->url(),
            ],
            'first_seen_at' => now(),
        ];
    }
}
