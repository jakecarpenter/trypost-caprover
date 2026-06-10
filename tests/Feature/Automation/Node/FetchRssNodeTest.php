<?php

declare(strict_types=1);

use App\Actions\Automation\Node\RunFetchRssNode;
use App\Enums\Automation\NodeRun\Status as NodeRunStatus;
use App\Jobs\Automation\ProcessAutomationNode;
use App\Models\Automation;
use App\Models\AutomationNodeState;
use App\Models\AutomationRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

afterEach(fn () => Carbon::setTestNow());

const FETCH_RSS_OLD = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"><channel>
  <item><title>Ancient</title><link>https://blog.example.com/1</link><guid>1</guid><pubDate>Mon, 01 Jan 2024 12:00:00 +0000</pubDate></item>
  <item><title>Old</title><link>https://blog.example.com/2</link><guid>2</guid><pubDate>Sat, 01 Feb 2025 12:00:00 +0000</pubDate></item>
</channel></rss>
XML;

const FETCH_RSS_MIXED = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"><channel>
  <item><title>Older</title><link>https://blog.example.com/a</link><guid>a</guid><pubDate>Sat, 01 Feb 2025 12:00:00 +0000</pubDate></item>
  <item><title>Newer1</title><link>https://blog.example.com/b</link><guid>b</guid><pubDate>Sun, 01 Jun 2025 12:00:00 +0000</pubDate></item>
  <item><title>Newer2</title><link>https://blog.example.com/c</link><guid>c</guid><pubDate>Mon, 15 Jun 2025 12:00:00 +0000</pubDate></item>
</channel></rss>
XML;

beforeEach(fn () => Bus::fake());

it('first execution dispatches nothing and stores watermark from newest item', function () {
    Carbon::setTestNow('2026-01-15 10:00:00');
    Http::fake(['blog.example.com/*' => Http::response(FETCH_RSS_OLD, 200)]);

    $automation = Automation::factory()->active()->create([
        'nodes' => [
            ['id' => 'trigger_1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['trigger_type' => 'schedule']],
            ['id' => 'fetch_1', 'type' => 'fetch_rss', 'position' => ['x' => 200, 'y' => 0], 'data' => ['feed_url' => 'https://blog.example.com/feed']],
            ['id' => 'end_1', 'type' => 'end', 'position' => ['x' => 400, 'y' => 0], 'data' => []],
        ],
        'connections' => [
            ['id' => 'e1', 'source' => 'trigger_1', 'target' => 'fetch_1'],
            ['id' => 'e2', 'source' => 'fetch_1', 'target' => 'end_1'],
        ],
    ]);

    $run = AutomationRun::factory()->for($automation)->create(['current_node_id' => 'fetch_1']);

    $result = app(RunFetchRssNode::class)($run, ['feed_url' => 'https://blog.example.com/feed']);

    expect($result->status)->toBe(NodeRunStatus::Completed);
    expect($result->nextHandle)->toBe('no_items');

    $state = AutomationNodeState::where('automation_id', $automation->id)->where('node_id', 'fetch_1')->first();
    expect($state)->not->toBeNull();
    expect(CarbonImmutable::parse($state->data['last_item_date'])->toIso8601String())
        ->toBe(CarbonImmutable::parse('2025-02-01 12:00:00')->toIso8601String());
});

it('processes the first new item on the current run and spawns siblings for the rest', function () {
    Carbon::setTestNow('2026-01-15 10:00:00');
    Http::fake(['blog.example.com/*' => Http::response(FETCH_RSS_MIXED, 200)]);

    $automation = Automation::factory()->active()->create([
        'nodes' => [
            ['id' => 'trigger_1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['trigger_type' => 'schedule']],
            ['id' => 'fetch_1', 'type' => 'fetch_rss', 'position' => ['x' => 200, 'y' => 0], 'data' => ['feed_url' => 'https://blog.example.com/feed']],
            ['id' => 'end_1', 'type' => 'end', 'position' => ['x' => 400, 'y' => 0], 'data' => []],
        ],
        'connections' => [
            ['id' => 'e1', 'source' => 'trigger_1', 'target' => 'fetch_1'],
            ['id' => 'e2', 'source' => 'fetch_1', 'target' => 'end_1'],
        ],
    ]);

    // Pre-seed watermark so 'Older' is below it; 'Newer1' and 'Newer2' should pass.
    AutomationNodeState::create([
        'automation_id' => $automation->id,
        'node_id' => 'fetch_1',
        'data' => ['last_item_date' => '2025-02-01T12:00:00+00:00'],
    ]);

    $run = AutomationRun::factory()->for($automation)->create(['current_node_id' => 'fetch_1']);

    $result = app(RunFetchRssNode::class)($run, ['feed_url' => 'https://blog.example.com/feed']);

    expect($result->status)->toBe(NodeRunStatus::Completed);
    expect($result->output['fetched']['key'])->toBe('b'); // oldest of the new items
    expect($result->output['fetch']['count'])->toBe(2);
    expect($result->output['fetch']['spawned'])->toBe(1);

    // One sibling run created + dispatched at end_1
    expect(AutomationRun::where('automation_id', $automation->id)->count())->toBe(2);
    Bus::assertDispatchedTimes(ProcessAutomationNode::class, 1);
});

it('fails when feed_url is missing', function () {
    $automation = Automation::factory()->active()->create();
    $run = AutomationRun::factory()->for($automation)->create(['current_node_id' => 'fetch_1']);

    $result = app(RunFetchRssNode::class)($run, []);

    expect($result->status)->toBe(NodeRunStatus::Failed);
});
