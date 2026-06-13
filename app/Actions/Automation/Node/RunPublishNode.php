<?php

declare(strict_types=1);

namespace App\Actions\Automation\Node;

use App\DataTransferObjects\Automation\NodeRunResult;
use App\Enums\Automation\Publish\Mode;
use App\Enums\Post\Status as PostStatus;
use App\Jobs\PublishPost;
use App\Models\AutomationRun;
use App\Models\Post;

class RunPublishNode
{
    public function __invoke(AutomationRun $run, array $config): NodeRunResult
    {
        $mode = Mode::from(data_get($config, 'mode', 'now'));

        // Dry runs never have a generated Post (RunGenerateNode skipped
        // persistence). Mirror the call site without touching the DB or
        // queueing PublishPost.
        if ($run->is_dry_run) {
            return NodeRunResult::completed(output: [
                'publish' => ['mode' => $mode->value, 'post_id' => null, 'dry_run' => true],
            ]);
        }

        $post = $run->generatedPost;

        if ($post === null) {
            return NodeRunResult::failed(__('automations.errors.no_generated_post'));
        }

        match ($mode) {
            Mode::Now => $this->publishNow($post),
            Mode::Scheduled => $this->schedule($post, (int) data_get($config, 'scheduled_offset')),
            Mode::Draft => null,
        };

        return NodeRunResult::completed(output: [
            'publish' => ['mode' => $mode->value, 'post_id' => $post->id],
        ]);
    }

    private function publishNow(Post $post): void
    {
        $post->update(['status' => PostStatus::Publishing]);
        PublishPost::dispatch($post);
    }

    private function schedule(Post $post, int $offsetMinutes): void
    {
        $post->update([
            'status' => PostStatus::Scheduled,
            'scheduled_at' => now()->addMinutes($offsetMinutes),
        ]);
    }
}
