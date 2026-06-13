<?php

declare(strict_types=1);

use App\Actions\Automation\Node\RunPublishNode;
use App\Enums\Post\Status as PostStatus;
use App\Jobs\PublishPost;
use App\Models\AutomationRun;
use App\Models\Post;
use Illuminate\Support\Facades\Queue;

it('leaves post in draft when mode is draft', function () {
    $post = Post::factory()->create(['status' => PostStatus::Draft]);
    $run = AutomationRun::factory()->create(['generated_post_id' => $post->id]);

    $result = app(RunPublishNode::class)($run, ['mode' => 'draft']);

    expect($result->status->value)->toBe('completed');
    expect($post->fresh()->status)->toBe(PostStatus::Draft);
});

it('schedules the post when mode is scheduled', function () {
    $post = Post::factory()->create(['status' => PostStatus::Draft]);
    $run = AutomationRun::factory()->create(['generated_post_id' => $post->id]);

    app(RunPublishNode::class)($run, ['mode' => 'scheduled', 'scheduled_offset' => 60]);

    $fresh = $post->fresh();
    expect($fresh->status)->toBe(PostStatus::Scheduled);
    expect($fresh->scheduled_at)->not->toBeNull();
});

it('fails when no generated post exists', function () {
    $run = AutomationRun::factory()->create(['generated_post_id' => null]);

    $result = app(RunPublishNode::class)($run, ['mode' => 'now']);

    expect($result->status->value)->toBe('failed');
});

it('dispatches PublishPost when mode is now', function () {
    Queue::fake();

    $post = Post::factory()->create(['status' => PostStatus::Draft]);
    $run = AutomationRun::factory()->create(['generated_post_id' => $post->id]);

    app(RunPublishNode::class)($run, ['mode' => 'now']);

    expect($post->fresh()->status)->toBe(PostStatus::Publishing);
    Queue::assertPushed(PublishPost::class);
});

it('does not publish or change the post on a dry run', function () {
    Queue::fake();

    $post = Post::factory()->create(['status' => PostStatus::Draft]);
    $run = AutomationRun::factory()->create(['generated_post_id' => $post->id, 'is_dry_run' => true]);

    $result = app(RunPublishNode::class)($run, ['mode' => 'now']);

    expect($result->status->value)->toBe('completed');
    expect($result->output['publish']['dry_run'])->toBeTrue();
    expect($post->fresh()->status)->toBe(PostStatus::Draft);
    Queue::assertNotPushed(PublishPost::class);
});
