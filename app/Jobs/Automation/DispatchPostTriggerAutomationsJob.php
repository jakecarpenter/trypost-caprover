<?php

declare(strict_types=1);

namespace App\Jobs\Automation;

use App\Actions\Automation\Trigger\DispatchPostTriggerAutomations;
use App\Enums\Automation\Trigger\Type as TriggerType;
use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchPostTriggerAutomationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public Post $post,
        public TriggerType $triggerType,
    ) {
        $this->onQueue('automations');
    }

    public function handle(DispatchPostTriggerAutomations $dispatch): void
    {
        $dispatch($this->post, $this->triggerType);
    }
}
