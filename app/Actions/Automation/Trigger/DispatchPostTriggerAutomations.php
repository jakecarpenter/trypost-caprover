<?php

declare(strict_types=1);

namespace App\Actions\Automation\Trigger;

use App\Actions\Automation\Run\AdvanceAutomationRun;
use App\Enums\Automation\Node\Type as NodeType;
use App\Enums\Automation\Run\Status as RunStatus;
use App\Enums\Automation\Status as AutomationStatus;
use App\Enums\Automation\Trigger\Type as TriggerType;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Post;

/**
 * Walks the workspace's active automations and dispatches a run for each one
 * whose Trigger node matches the given post-related event (PostPublished /
 * PostScheduled). The post payload is placed at `context.trigger.post` so
 * downstream nodes can reference it via templates like `{{ trigger.post.id }}`.
 *
 * V1 limitation: every post fires triggers, regardless of whether the post
 * itself was created by an automation. If a future use case introduces loops
 * (automation X publishes → trigger fires → X publishes again), we'll need a
 * `posts.created_by_automation_run_id` column to skip them.
 */
class DispatchPostTriggerAutomations
{
    public function __construct(private AdvanceAutomationRun $advance) {}

    public function __invoke(Post $post, TriggerType $triggerType): void
    {
        $automations = Automation::query()
            ->where('workspace_id', $post->workspace_id)
            ->where('status', AutomationStatus::Active)
            ->where('trigger_type', $triggerType->value)
            ->get();

        foreach ($automations as $automation) {
            $triggerNode = collect($automation->nodes ?? [])->firstWhere('type', NodeType::Trigger->value);

            if ($triggerNode === null) {
                continue;
            }

            $this->dispatchRun($automation, $triggerNode, $post);
        }
    }

    private function dispatchRun(Automation $automation, array $triggerNode, Post $post): void
    {
        $context = [
            'trigger' => [
                'event' => $triggerNode['data']['trigger_type'],
                'fired_at' => now()->toIso8601String(),
                'post' => [
                    'id' => $post->id,
                    'content' => $post->content,
                    'status' => $post->status->value,
                    'scheduled_at' => $post->scheduled_at?->toIso8601String(),
                    'published_at' => $post->published_at?->toIso8601String(),
                ],
            ],
        ];

        $run = AutomationRun::create([
            'automation_id' => $automation->id,
            'status' => RunStatus::Pending,
            'context' => $context,
        ]);

        $targets = $this->advance->targetsFor($automation, $triggerNode['id']);

        if ($targets === []) {
            $run->update([
                'status' => RunStatus::Failed,
                'error' => ['message' => __('automations.errors.no_trigger_connection')],
                'finished_at' => now(),
            ]);

            return;
        }

        $this->advance->dispatchBranches($run, $targets);
    }
}
