<?php

declare(strict_types=1);

use App\Actions\Automation\Node\RunHttpRequestNode;
use App\Enums\Automation\NodeRun\Status as NodeRunStatus;
use App\Jobs\Automation\ProcessAutomationNode;
use App\Models\Automation;
use App\Models\AutomationNodeState;
use App\Models\AutomationRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

afterEach(fn () => Carbon::setTestNow());
beforeEach(fn () => Bus::fake());

it('runs in single-response mode when items_path is empty', function () {
    Http::fake([
        '1.1.1.1/*' => Http::response(['title' => 'Hello', 'id' => 42], 200),
    ]);

    $automation = Automation::factory()->active()->create();
    $run = AutomationRun::factory()->for($automation)->create(['current_node_id' => 'http_1']);

    $result = app(RunHttpRequestNode::class)($run, [
        'url' => 'https://1.1.1.1/lookup',
        'method' => 'GET',
        'auth_type' => 'none',
    ]);

    expect($result->status)->toBe(NodeRunStatus::Completed);
    expect($result->output['fetched'])->toBe(['title' => 'Hello', 'id' => 42]);
    expect($result->output['fetch']['spawned'])->toBe(0);
});

it('blocks a request to a private or reserved address', function () {
    Http::fake();

    $automation = Automation::factory()->active()->create();
    $run = AutomationRun::factory()->for($automation)->create(['current_node_id' => 'http_1']);

    $result = app(RunHttpRequestNode::class)($run, [
        'url' => 'http://127.0.0.1/internal',
        'method' => 'GET',
        'auth_type' => 'none',
    ]);

    expect($result->status)->toBe(NodeRunStatus::Failed);
    expect($result->error['reason'])->toBe('url_not_allowed');
    Http::assertNothingSent();
});

it('processes first new item and spawns siblings when items_path is set', function () {
    Carbon::setTestNow('2026-01-15 10:00:00');
    Http::fake([
        '1.1.1.1/*' => Http::response([
            'items' => [
                ['id' => 'a1', 'published_at' => '2025-12-31T00:00:00Z'],
                ['id' => 'a2', 'published_at' => '2026-01-05T00:00:00Z'],
                ['id' => 'a3', 'published_at' => '2026-01-10T00:00:00Z'],
            ],
        ], 200),
    ]);

    $automation = Automation::factory()->active()->create([
        'nodes' => [
            ['id' => 'trigger_1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['trigger_type' => 'schedule']],
            ['id' => 'http_1', 'type' => 'http_request', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
            ['id' => 'end_1', 'type' => 'end', 'position' => ['x' => 400, 'y' => 0], 'data' => []],
        ],
        'connections' => [
            ['id' => 'e1', 'source' => 'trigger_1', 'target' => 'http_1'],
            ['id' => 'e2', 'source' => 'http_1', 'target' => 'end_1'],
        ],
    ]);

    AutomationNodeState::create([
        'automation_id' => $automation->id,
        'node_id' => 'http_1',
        'data' => ['last_item_date' => '2025-12-30T00:00:00+00:00'],
    ]);

    $run = AutomationRun::factory()->for($automation)->create(['current_node_id' => 'http_1']);

    $result = app(RunHttpRequestNode::class)($run, [
        'url' => 'https://1.1.1.1/items',
        'method' => 'GET',
        'auth_type' => 'none',
        'items_path' => 'items',
        'item_key_path' => 'id',
        'item_date_path' => 'published_at',
    ]);

    expect($result->output['fetched']['id'])->toBe('a1');
    expect($result->output['fetch']['count'])->toBe(3);
    expect($result->output['fetch']['spawned'])->toBe(2);
    Bus::assertDispatchedTimes(ProcessAutomationNode::class, 2);
});

it('sends bearer token header decrypting it on the fly', function () {
    Http::fake(['1.1.1.1/*' => Http::response(['ok' => true], 200)]);

    $automation = Automation::factory()->active()->create();
    $run = AutomationRun::factory()->for($automation)->create(['current_node_id' => 'http_1']);

    app(RunHttpRequestNode::class)($run, [
        'url' => 'https://1.1.1.1/me',
        'method' => 'GET',
        'auth_type' => 'bearer',
        'auth_token' => Crypt::encryptString('secret-token-123'),
    ]);

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer secret-token-123'));
});

it('sends api_key header with custom header name', function () {
    Http::fake(['1.1.1.1/*' => Http::response(['ok' => true], 200)]);

    $automation = Automation::factory()->active()->create();
    $run = AutomationRun::factory()->for($automation)->create(['current_node_id' => 'http_1']);

    app(RunHttpRequestNode::class)($run, [
        'url' => 'https://1.1.1.1/me',
        'method' => 'GET',
        'auth_type' => 'api_key',
        'auth_header_name' => 'X-Custom-Key',
        'auth_token' => 'plain-text-key',
    ]);

    Http::assertSent(fn ($request) => $request->hasHeader('X-Custom-Key', 'plain-text-key'));
});

it('posts a body rendered from the template with run context', function () {
    Http::fake(['1.1.1.1/*' => Http::response(['ok' => true], 200)]);

    $automation = Automation::factory()->active()->create();
    $run = AutomationRun::factory()->for($automation)->create([
        'current_node_id' => 'http_1',
        'context' => ['trigger' => ['post' => ['id' => 'post-42']]],
    ]);

    app(RunHttpRequestNode::class)($run, [
        'url' => 'https://1.1.1.1/notify',
        'method' => 'POST',
        'auth_type' => 'none',
        'body_template' => '{"post_id":"{{ trigger.post.id }}"}',
    ]);

    Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['post_id'] === 'post-42');
});

it('sends the branded user-agent header', function () {
    Http::fake(['1.1.1.1/*' => Http::response(['ok' => true], 200)]);

    $automation = Automation::factory()->active()->create();
    $run = AutomationRun::factory()->for($automation)->create(['current_node_id' => 'http_1']);

    app(RunHttpRequestNode::class)($run, [
        'url' => 'https://1.1.1.1/me',
        'method' => 'GET',
        'auth_type' => 'none',
        'headers' => ['User-Agent' => 'user-supplied-agent'],
    ]);

    Http::assertSent(fn ($request) => $request->hasHeader('User-Agent', config('trypost.user_agent')));
});

it('sends custom headers configured in the editor', function () {
    Http::fake(['1.1.1.1/*' => Http::response(['ok' => true], 200)]);

    $automation = Automation::factory()->active()->create();
    $run = AutomationRun::factory()->for($automation)->create(['current_node_id' => 'http_1']);

    app(RunHttpRequestNode::class)($run, [
        'url' => 'https://1.1.1.1/me',
        'method' => 'GET',
        'auth_type' => 'none',
        'headers' => ['X-Custom' => 'v'],
    ]);

    Http::assertSent(fn ($request) => $request->hasHeader('X-Custom', 'v'));
});

it('fails when url is missing', function () {
    $automation = Automation::factory()->active()->create();
    $run = AutomationRun::factory()->for($automation)->create(['current_node_id' => 'http_1']);

    $result = app(RunHttpRequestNode::class)($run, []);

    expect($result->status)->toBe(NodeRunStatus::Failed);
});
