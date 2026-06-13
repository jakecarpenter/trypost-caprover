<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\Automation\Trigger\Type as TriggerType;
use App\Enums\Post\Status as PostStatus;
use App\Jobs\Automation\DispatchPostTriggerAutomationsJob;
use App\Models\Post;

class PostObserver
{
    public function saved(Post $post): void
    {
        if (! $post->wasChanged('status')) {
            return;
        }

        $triggerType = match ($post->status) {
            PostStatus::Published => TriggerType::PostPublished,
            PostStatus::Scheduled => TriggerType::PostScheduled,
            default => null,
        };

        if ($triggerType === null) {
            return;
        }

        DispatchPostTriggerAutomationsJob::dispatch($post, $triggerType)->afterCommit();
    }
}
