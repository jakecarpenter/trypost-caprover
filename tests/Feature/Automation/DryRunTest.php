<?php

declare(strict_types=1);

use App\Actions\Automation\Node\RunFetchRssNode;
use App\Actions\Automation\Node\RunGenerateNode;
use App\Actions\Automation\Node\RunHttpRequestNode;
use App\Actions\Automation\Node\RunPublishNode;
use App\Actions\Automation\Run\TestAutomation;
use App\Ai\Agents\PostContentGenerator;
use App\Ai\Agents\PostContentHumanizer;
use App\Enums\UserWorkspace\Role;
use App\Jobs\Automation\ProcessAutomationNode;
use App\Jobs\PublishPost;
use App\Models\Automation;
use App\Models\AutomationNodeState;
use App\Models\AutomationRun;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Bus::fake();

    $this->workspace = Workspace::factory()->create();
    $this->user = User::factory()->create(['current_workspace_id' => $this->workspace->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Admin->value]);
});

it('defaults to dry mode when with_real_data is omitted', function () {
    $automation = Automation::factory()->for($this->workspace)->withScheduleTrigger()->create();
    $automation->update([
        'nodes' => array_merge($automation->nodes, [
            ['id' => 'end_1', 'type' => 'end', 'position' => ['x' => 1, 'y' => 1], 'data' => []],
        ]),
        'connections' => [['id' => 'e1', 'source' => 'trigger_1', 'target' => 'end_1']],
    ]);

    $run = app(TestAutomation::class)($automation);

    expect($run->is_dry_run)->toBeTrue();
    expect($run->is_manual)->toBeTrue();
});

it('flags the run as non-dry when withRealData is true', function () {
    $automation = Automation::factory()->for($this->workspace)->withScheduleTrigger()->create();
    $automation->update([
        'nodes' => array_merge($automation->nodes, [
            ['id' => 'end_1', 'type' => 'end', 'position' => ['x' => 1, 'y' => 1], 'data' => []],
        ]),
        'connections' => [['id' => 'e1', 'source' => 'trigger_1', 'target' => 'end_1']],
    ]);

    $run = app(TestAutomation::class)($automation, withRealData: true);

    expect($run->is_dry_run)->toBeFalse();
});

it('controller sends with_real_data through to the action', function () {
    $automation = Automation::factory()->for($this->workspace)->create([
        'nodes' => [
            ['id' => 'trigger_1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['trigger_type' => 'schedule']],
            ['id' => 'end_1', 'type' => 'end', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
        ],
        'connections' => [['id' => 'e1', 'source' => 'trigger_1', 'target' => 'end_1']],
    ]);

    $this->actingAs($this->user)
        ->postJson(route('app.automations.test', $automation->id), ['with_real_data' => true])
        ->assertOk();

    expect(AutomationRun::query()->where('automation_id', $automation->id)->first()->is_dry_run)->toBeFalse();
});

it('controller defaults with_real_data to false when not provided', function () {
    $automation = Automation::factory()->for($this->workspace)->create([
        'nodes' => [
            ['id' => 'trigger_1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['trigger_type' => 'schedule']],
            ['id' => 'end_1', 'type' => 'end', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
        ],
        'connections' => [['id' => 'e1', 'source' => 'trigger_1', 'target' => 'end_1']],
    ]);

    $this->actingAs($this->user)
        ->postJson(route('app.automations.test', $automation->id), [])
        ->assertOk();

    expect(AutomationRun::query()->where('automation_id', $automation->id)->first()->is_dry_run)->toBeTrue();
});

it('does not persist a Post when generate node runs in dry mode', function () {
    PostContentGenerator::fake([
        ['content' => 'hello', 'image_title' => 't', 'image_body' => 'b', 'image_keywords' => ['k']],
    ]);
    PostContentHumanizer::fake([
        ['content' => 'hello humanized', 'image_title' => 't', 'image_body' => 'b'],
    ]);

    $automation = Automation::factory()->for($this->workspace)->create();
    $run = AutomationRun::factory()->for($automation)->create(['is_dry_run' => true]);

    $postsBefore = Post::query()->count();

    $result = app(RunGenerateNode::class)($run, [
        'accounts' => [],
        'prompt_template' => 'topic',
        'image_source' => 'none',
    ]);

    expect($result->status->value)->toBe('completed');
    expect(Post::query()->count())->toBe($postsBefore);
    expect($result->output['generated']['post_id'])->toBeNull();
    expect($result->output['generated']['dry_run'])->toBeTrue();
    expect($result->output['generated']['content'])->toBe('hello humanized');
    expect($run->fresh()->generated_post_id)->toBeNull();
});

it('does not mutate the post or dispatch PublishPost when publish node runs in dry mode', function () {
    Queue::fake();

    $automation = Automation::factory()->for($this->workspace)->create();
    $run = AutomationRun::factory()->for($automation)->create([
        'is_dry_run' => true,
        'generated_post_id' => null,
    ]);

    $result = app(RunPublishNode::class)($run, ['mode' => 'now']);

    expect($result->status->value)->toBe('completed');
    expect($result->output['publish']['dry_run'])->toBeTrue();
    expect($result->output['publish']['post_id'])->toBeNull();
    Queue::assertNotPushed(PublishPost::class);
});

it('does not advance http watermark or spawn siblings in dry mode', function () {
    Http::fake([
        '8.8.8.8/*' => Http::response([
            'items' => [
                ['id' => 'a1', 'published_at' => '2026-01-01T00:00:00Z'],
                ['id' => 'a2', 'published_at' => '2026-01-02T00:00:00Z'],
                ['id' => 'a3', 'published_at' => '2026-01-03T00:00:00Z'],
            ],
        ], 200),
    ]);

    $automation = Automation::factory()->for($this->workspace)->create([
        'nodes' => [
            ['id' => 'http_1', 'type' => 'http_request', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
            ['id' => 'end_1', 'type' => 'end', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
        ],
        'connections' => [['id' => 'e1', 'source' => 'http_1', 'target' => 'end_1']],
    ]);

    $run = AutomationRun::factory()->for($automation)->create([
        'is_dry_run' => true,
        'current_node_id' => 'http_1',
    ]);

    $result = app(RunHttpRequestNode::class)($run, [
        'url' => 'https://8.8.8.8/items',
        'method' => 'GET',
        'auth_type' => 'none',
        'items_path' => 'items',
        'item_key_path' => 'id',
        'item_date_path' => 'published_at',
    ]);

    expect($result->output['fetched']['id'])->toBeIn(['a1', 'a2', 'a3']);
    expect($result->output['fetch']['spawned'])->toBe(0);
    expect(AutomationNodeState::query()->where('automation_id', $automation->id)->exists())->toBeFalse();
    Bus::assertNotDispatched(ProcessAutomationNode::class);
});

it('still hits the external URL in dry mode regardless of HTTP method', function () {
    Http::fake(['8.8.8.8/*' => Http::response(['ok' => true], 200)]);

    $automation = Automation::factory()->for($this->workspace)->create();
    $run = AutomationRun::factory()->for($automation)->create([
        'is_dry_run' => true,
        'current_node_id' => 'http_1',
    ]);

    app(RunHttpRequestNode::class)($run, [
        'url' => 'https://8.8.8.8/notify',
        'method' => 'POST',
        'auth_type' => 'none',
        'body_template' => '{"x": 1}',
    ]);

    Http::assertSent(fn ($request) => $request->method() === 'POST' && $request->url() === 'https://8.8.8.8/notify');
});

it('does not advance rss watermark or spawn siblings in dry mode', function () {
    $rss = <<<'XML'
        <?xml version="1.0"?>
        <rss><channel>
            <item><guid>a</guid><title>A</title><link>https://x/a</link><description>d</description><pubDate>Mon, 05 Jan 2026 10:00:00 +0000</pubDate></item>
            <item><guid>b</guid><title>B</title><link>https://x/b</link><description>d</description><pubDate>Mon, 06 Jan 2026 10:00:00 +0000</pubDate></item>
        </channel></rss>
        XML;

    Http::fake(['1.1.1.1/*' => Http::response($rss, 200)]);

    $automation = Automation::factory()->for($this->workspace)->create();
    $run = AutomationRun::factory()->for($automation)->create([
        'is_dry_run' => true,
        'current_node_id' => 'rss_1',
    ]);

    $result = app(RunFetchRssNode::class)($run, ['feed_url' => 'https://1.1.1.1/rss']);

    expect($result->status->value)->toBe('completed');
    expect($result->output['fetch']['spawned'])->toBe(0);
    expect(AutomationNodeState::query()->where('automation_id', $automation->id)->exists())->toBeFalse();
    Bus::assertNotDispatched(ProcessAutomationNode::class);
});

it('productionRuns scope filters out dry runs and manual test runs', function () {
    $automation = Automation::factory()->for($this->workspace)->create();
    AutomationRun::factory()->for($automation)->count(2)->create(['is_manual' => false, 'is_dry_run' => false]);
    AutomationRun::factory()->for($automation)->count(3)->create(['is_dry_run' => true]);
    AutomationRun::factory()->for($automation)->create(['is_manual' => true, 'is_dry_run' => false]);

    expect(AutomationRun::query()->count())->toBe(6);
    expect(AutomationRun::query()->productionRuns()->count())->toBe(2);
});

it('does not show dry runs in the invocations history', function () {
    $automation = Automation::factory()->for($this->workspace)->create();
    $real = AutomationRun::factory()->for($automation)->create(['is_dry_run' => false]);
    AutomationRun::factory()->for($automation)->create(['is_dry_run' => true]);

    $this->actingAs($this->user)
        ->get(route('app.automations.invocations', $automation->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('invocations.data', 1)
            ->where('invocations.data.0.id', $real->id)
        );
});

it('regression: a real test run still creates the Post', function () {
    PostContentGenerator::fake([
        ['content' => 'real', 'image_title' => 't', 'image_body' => 'b', 'image_keywords' => ['k']],
    ]);
    PostContentHumanizer::fake([
        ['content' => 'real humanized', 'image_title' => 't', 'image_body' => 'b'],
    ]);

    $automation = Automation::factory()->for($this->workspace)->create();
    $run = AutomationRun::factory()->for($automation)->create(['is_dry_run' => false]);

    $postsBefore = Post::query()->count();

    app(RunGenerateNode::class)($run, [
        'accounts' => [],
        'prompt_template' => 'topic',
        'image_source' => 'none',
    ]);

    expect(Post::query()->count())->toBe($postsBefore + 1);
    expect($run->fresh()->generated_post_id)->not->toBeNull();
});
