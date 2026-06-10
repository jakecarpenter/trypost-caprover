<?php

declare(strict_types=1);

use App\Enums\Post\Status as PostStatus;
use App\Jobs\Automation\ProcessAutomationNode;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Post;
use App\Models\Workspace;
use Illuminate\Support\Facades\Bus;

beforeEach(fn () => Bus::fake());

it('dispatches a run when a post becomes published in the workspace', function () {
    $workspace = Workspace::factory()->create();
    $automation = Automation::factory()->active()->for($workspace)->create([
        'nodes' => [
            ['id' => 'trigger_1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['trigger_type' => 'post_published']],
            ['id' => 'end_1', 'type' => 'end', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
        ],
        'connections' => [['id' => 'e1', 'source' => 'trigger_1', 'target' => 'end_1']],
    ]);

    $post = Post::factory()->for($workspace)->create(['status' => PostStatus::Draft]);
    $post->update(['status' => PostStatus::Published]);

    $runs = AutomationRun::where('automation_id', $automation->id)->get();
    expect($runs)->toHaveCount(1);
    expect($runs->first()->context['trigger']['event'])->toBe('post_published');
    expect($runs->first()->context['trigger']['post']['id'])->toBe($post->id);

    Bus::assertDispatched(ProcessAutomationNode::class);
});

it('dispatches when a post becomes scheduled', function () {
    $workspace = Workspace::factory()->create();
    $automation = Automation::factory()->active()->for($workspace)->create([
        'nodes' => [
            ['id' => 'trigger_1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['trigger_type' => 'post_scheduled']],
            ['id' => 'end_1', 'type' => 'end', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
        ],
        'connections' => [['id' => 'e1', 'source' => 'trigger_1', 'target' => 'end_1']],
    ]);

    $post = Post::factory()->for($workspace)->create(['status' => PostStatus::Draft]);
    $post->update(['status' => PostStatus::Scheduled]);

    expect(AutomationRun::where('automation_id', $automation->id)->count())->toBe(1);
});

it('does not dispatch for automations in a different workspace', function () {
    $workspaceA = Workspace::factory()->create();
    $workspaceB = Workspace::factory()->create();

    $automation = Automation::factory()->active()->for($workspaceA)->create([
        'nodes' => [
            ['id' => 'trigger_1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['trigger_type' => 'post_published']],
            ['id' => 'end_1', 'type' => 'end', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
        ],
        'connections' => [['id' => 'e1', 'source' => 'trigger_1', 'target' => 'end_1']],
    ]);

    $post = Post::factory()->for($workspaceB)->create(['status' => PostStatus::Draft]);
    $post->update(['status' => PostStatus::Published]);

    expect(AutomationRun::where('automation_id', $automation->id)->count())->toBe(0);
});

it('does not dispatch when status change is not to Published or Scheduled', function () {
    $workspace = Workspace::factory()->create();
    Automation::factory()->active()->for($workspace)->create([
        'nodes' => [
            ['id' => 'trigger_1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['trigger_type' => 'post_published']],
            ['id' => 'end_1', 'type' => 'end', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
        ],
        'connections' => [['id' => 'e1', 'source' => 'trigger_1', 'target' => 'end_1']],
    ]);

    $post = Post::factory()->for($workspace)->create(['status' => PostStatus::Draft]);
    $post->update(['status' => PostStatus::Publishing]);

    expect(AutomationRun::count())->toBe(0);
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

    $post = Post::factory()->for($workspace)->create(['status' => PostStatus::Draft]);
    $post->update(['status' => PostStatus::Published]);

    expect(AutomationRun::count())->toBe(0);
});
