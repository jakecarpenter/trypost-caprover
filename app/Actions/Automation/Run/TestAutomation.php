<?php

declare(strict_types=1);

namespace App\Actions\Automation\Run;

use App\Enums\Automation\Run\Status;
use App\Enums\Automation\Trigger\Type as TriggerType;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Post;
use App\Services\Automation\AutomationConfigValidator;
use DomainException;

/**
 * Kicks off a manual run from the editor without waiting for the real trigger
 * to fire. Mirrors n8n's "Execute workflow" — the same job pipeline runs, but
 * the trigger payload is synthesized so the user can see exactly what each
 * node does end-to-end. Runs are flagged `is_manual=true` so they're filtered
 * from production audit views.
 *
 * When `$withRealData` is false (the default), the run is marked `is_dry_run`
 * so side-effectful nodes (publish, generate, watermark advancement, sibling
 * spawning) short-circuit. Dry-run rows are kept briefly so the editor test
 * panel can show the completed result, then reaped by the scheduled
 * `automation:prune-dry-runs` command once past its grace window.
 */
class TestAutomation
{
    public function __construct(
        private AdvanceAutomationRun $advance,
        private AutomationConfigValidator $configValidator,
    ) {}

    public function __invoke(Automation $automation, bool $withRealData = false): AutomationRun
    {
        $issue = $this->configValidator->firstMessage($automation->nodes ?? []);

        if ($issue !== null) {
            throw new DomainException($issue);
        }

        $triggerNode = collect($automation->nodes ?? [])->firstWhere('type', 'trigger');
        $context = ['trigger' => $this->synthesizePayload($automation, $triggerNode ?? [])];

        $targets = $triggerNode !== null
            ? $this->advance->targetsFor($automation, $triggerNode['id'])
            : [];

        $run = AutomationRun::create([
            'automation_id' => $automation->id,
            'status' => Status::Pending,
            'is_manual' => true,
            'is_dry_run' => ! $withRealData,
            'context' => $context,
        ]);

        if ($targets === []) {
            $run->update([
                'status' => Status::Failed,
                'error' => ['message' => __('automations.errors.no_trigger_connection')],
                'finished_at' => now(),
            ]);

            return $run;
        }

        $this->advance->dispatchBranches($run, $targets);

        return $run;
    }

    /**
     * @param  array<string, mixed>  $triggerNode
     * @return array<string, mixed>
     */
    private function synthesizePayload(Automation $automation, array $triggerNode): array
    {
        $type = data_get($triggerNode, 'data.trigger_type');

        return match ($type) {
            TriggerType::PostPublished->value, TriggerType::PostScheduled->value => $this->synthesizePostPayload($automation, (string) $type),
            default => ['event' => $type ?? TriggerType::Schedule->value, 'fired_at' => now()->toIso8601String(), 'manual' => true],
        };
    }

    /**
     * Picks the most recent post in the automation's workspace so the test run
     * reflects something the user actually sees. Falls back to a placeholder
     * payload when the workspace has no posts yet.
     *
     * @return array<string, mixed>
     */
    private function synthesizePostPayload(Automation $automation, string $event): array
    {
        $post = Post::query()
            ->where('workspace_id', $automation->workspace_id)
            ->latest()
            ->first();

        $base = [
            'event' => $event,
            'fired_at' => now()->toIso8601String(),
            'manual' => true,
        ];

        if ($post === null) {
            return array_merge($base, ['post' => null, 'fetch_error' => 'no posts in workspace']);
        }

        return array_merge($base, [
            'post' => [
                'id' => $post->id,
                'content' => $post->content,
                'status' => $post->status->value,
                'scheduled_at' => $post->scheduled_at?->toIso8601String(),
                'published_at' => $post->published_at?->toIso8601String(),
            ],
        ]);
    }
}
