<?php

declare(strict_types=1);

use App\Actions\Automation\Trigger\DispatchPostTriggerAutomations;
use App\Enums\Automation\Trigger\Type as TriggerType;
use App\Enums\Post\Status as PostStatus;
use App\Jobs\Automation\DispatchPostTriggerAutomationsJob;
use App\Jobs\Automation\ProcessAutomationNode;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Post;
use App\Models\Workspace;
use Illuminate\Support\Facades\Bus;

beforeEach(fn () => Bus::fake());

it('dispatches the trigger job when a post becomes published', function () {
    $post = Post::factory()->create(['status' => PostStatus::Draft]);
    $post->update(['status' => PostStatus::Published]);

    Bus::assertDispatched(
        DispatchPostTriggerAutomationsJob::class,
        fn (DispatchPostTriggerAutomationsJob $job) => $job->post->is($post)
            && $job->triggerType === TriggerType::PostPublished,
    );
});

it('dispatches the trigger job when a post becomes scheduled', function () {
    $post = Post::factory()->create(['status' => PostStatus::Draft]);
    $post->update(['status' => PostStatus::Scheduled]);

    Bus::assertDispatched(
        DispatchPostTriggerAutomationsJob::class,
        fn (DispatchPostTriggerAutomationsJob $job) => $job->triggerType === TriggerType::PostScheduled,
    );
});

it('does not dispatch for status changes other than published or scheduled', function () {
    $post = Post::factory()->create(['status' => PostStatus::Draft]);
    $post->update(['status' => PostStatus::Publishing]);

    Bus::assertNotDispatched(DispatchPostTriggerAutomationsJob::class);
});

it('does not dispatch when the status did not change', function () {
    $post = Post::factory()->create(['status' => PostStatus::Draft]);
    $post->update(['content' => 'edited body']);

    Bus::assertNotDispatched(DispatchPostTriggerAutomationsJob::class);
});

it('creates a run and advances to the next node for a matching active automation', function () {
    $workspace = Workspace::factory()->create();
    $automation = Automation::factory()->active()->for($workspace)->create([
        'nodes' => [
            ['id' => 'trigger_1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['trigger_type' => 'post_published']],
            ['id' => 'end_1', 'type' => 'end', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
        ],
        'connections' => [['id' => 'e1', 'source' => 'trigger_1', 'target' => 'end_1']],
    ]);

    $post = Post::factory()->for($workspace)->create(['status' => PostStatus::Published]);

    app(DispatchPostTriggerAutomations::class)($post, TriggerType::PostPublished);

    $runs = AutomationRun::where('automation_id', $automation->id)->get();
    expect($runs)->toHaveCount(1);
    expect($runs->first()->context['trigger']['event'])->toBe('post_published');
    expect($runs->first()->context['trigger']['post']['id'])->toBe($post->id);

    Bus::assertDispatched(ProcessAutomationNode::class);
});

it('does not create a run for automations in a different workspace', function () {
    $workspaceA = Workspace::factory()->create();
    $workspaceB = Workspace::factory()->create();

    $automation = Automation::factory()->active()->for($workspaceA)->create([
        'nodes' => [
            ['id' => 'trigger_1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['trigger_type' => 'post_published']],
            ['id' => 'end_1', 'type' => 'end', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
        ],
        'connections' => [['id' => 'e1', 'source' => 'trigger_1', 'target' => 'end_1']],
    ]);

    $post = Post::factory()->for($workspaceB)->create(['status' => PostStatus::Published]);

    app(DispatchPostTriggerAutomations::class)($post, TriggerType::PostPublished);

    expect(AutomationRun::where('automation_id', $automation->id)->count())->toBe(0);
});

it('skips paused automations', function () {
    $workspace = Workspace::factory()->create();
    Automation::factory()->paused()->for($workspace)->create([
        'nodes' => [
            ['id' => 'trigger_1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['trigger_type' => 'post_published']],
            ['id' => 'end_1', 'type' => 'end', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
        ],
        'connections' => [['id' => 'e1', 'source' => 'trigger_1', 'target' => 'end_1']],
    ]);

    $post = Post::factory()->for($workspace)->create(['status' => PostStatus::Published]);

    app(DispatchPostTriggerAutomations::class)($post, TriggerType::PostPublished);

    expect(AutomationRun::count())->toBe(0);
});
