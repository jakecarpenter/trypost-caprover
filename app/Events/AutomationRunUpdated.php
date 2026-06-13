<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\AutomationRun;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Lightweight signal that a run (or one of its node runs) advanced. Carries
 * only identifiers — the client refetches the full state via the show-run
 * endpoint to keep resource serialization centralized.
 */
class AutomationRunUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public AutomationRun $run) {}

    public function broadcastAs(): string
    {
        return 'automation.run.updated';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("automation.{$this->run->automation_id}"),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return [
            'run_id' => $this->run->id,
            'root_run_id' => $this->run->rootId(),
            'automation_id' => $this->run->automation_id,
            'status' => $this->run->status->value,
        ];
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
