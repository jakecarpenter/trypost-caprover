<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TelegramConnectFailed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $workspaceId,
        public string $nonce,
        public string $reason,
    ) {}

    public function broadcastAs(): string
    {
        return 'telegram.connect.failed';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("workspace.{$this->workspaceId}"),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return [
            'nonce' => $this->nonce,
            'reason' => $this->reason,
        ];
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
