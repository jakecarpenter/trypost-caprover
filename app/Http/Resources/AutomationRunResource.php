<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AutomationRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'automation_id' => $this->automation_id,
            'trigger_item_id' => $this->trigger_item_id,
            'current_node_id' => $this->current_node_id,
            'status' => $this->status->value,
            'is_manual' => (bool) $this->is_manual,
            'is_dry_run' => (bool) $this->is_dry_run,
            'next_action_at' => $this->next_action_at,
            'generated_post_id' => $this->generated_post_id,
            'context' => $this->context,
            'error' => $this->error,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
        ];
    }
}
