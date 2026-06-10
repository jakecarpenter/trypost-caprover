<?php

declare(strict_types=1);

use App\Actions\Automation\Run\TestAutomation;
use App\Enums\Automation\Run\Status;
use App\Enums\Post\Status as PostStatus;
use App\Jobs\Automation\ProcessAutomationNode;
use App\Models\Automation;
use App\Models\Post;
use App\Models\Workspace;
use Illuminate\Support\Facades\Bus;

beforeEach(fn () => Bus::fake());

it('creates a manual run for a schedule trigger with synthesized payload', function () {
    $automation = Automation::factory()->active()->create([
        'nodes' => [
            ['id' => 'trigger_1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0],
                'data' => ['trigger_type' => 'schedule', 'cron' => '0 9 * * *']],
            ['id' => 'end_1', 'type' => 'end', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
        ],
        'connections' => [['id' => 'e1', 'source' => 'trigger_1', 'target' => 'end_1']],
    ]);

    $run = app(TestAutomation::class)($automation);

    expect($run->is_manual)->toBeTrue();
    expect($run->status)->toBe(Status::Pending);
    expect($run->context['trigger'])->toHaveKey('fired_at');
    expect($run->context['trigger']['manual'])->toBeTrue();
    expect($run->context['trigger']['event'])->toBe('schedule');

    Bus::assertDispatched(ProcessAutomationNode::class);
});

it('synthesizes a post payload from the workspace latest post when trigger is post_published', function () {
    $workspace = Workspace::factory()->create();
    $post = Post::factory()->for($workspace)->create([
        'content' => 'Hello world',
        'status' => PostStatus::Published,
    ]);

    $automation = Automation::factory()->active()->for($workspace)->create([
        'nodes' => [
            ['id' => 'trigger_1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0],
                'data' => ['trigger_type' => 'post_published']],
            ['id' => 'end_1', 'type' => 'end', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
        ],
        'connections' => [['id' => 'e1', 'source' => 'trigger_1', 'target' => 'end_1']],
    ]);

    $run = app(TestAutomation::class)($automation);

    expect($run->is_manual)->toBeTrue();
    expect($run->context['trigger']['event'])->toBe('post_published');
    expect($run->context['trigger']['post']['id'])->toBe($post->id);
    expect($run->context['trigger']['post']['content'])->toBe('Hello world');
    expect($run->context['trigger']['manual'])->toBeTrue();
});

it('synthesizes payload with null post when workspace has no posts', function () {
    $workspace = Workspace::factory()->create();
    $automation = Automation::factory()->active()->for($workspace)->create([
        'nodes' => [
            ['id' => 'trigger_1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0],
                'data' => ['trigger_type' => 'post_scheduled']],
            ['id' => 'end_1', 'type' => 'end', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
        ],
        'connections' => [['id' => 'e1', 'source' => 'trigger_1', 'target' => 'end_1']],
    ]);

    $run = app(TestAutomation::class)($automation);

    expect($run->context['trigger']['post'])->toBeNull();
    expect($run->context['trigger']['fetch_error'])->toBe('no posts in workspace');
});

it('marks the run as failed when trigger has no outgoing connection', function () {
    $automation = Automation::factory()->active()->create([
        'nodes' => [
            ['id' => 'trigger_1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0],
                'data' => ['trigger_type' => 'schedule', 'cron' => '0 9 * * *']],
        ],
        'connections' => [],
    ]);

    $run = app(TestAutomation::class)($automation);

    expect($run->is_manual)->toBeTrue();
    expect($run->status)->toBe(Status::Failed);
    expect($run->error['message'])->toContain('Trigger');

    Bus::assertNotDispatched(ProcessAutomationNode::class);
});
