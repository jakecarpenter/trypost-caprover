<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AutomationTriggerItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_key' => $this->item_key,
            'payload' => $this->payload,
            'first_seen_at' => $this->first_seen_at,
            'run' => AutomationRunResource::make($this->whenLoaded('run')),
        ];
    }
}
