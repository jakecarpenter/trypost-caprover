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
  <item><title>Ancient</title><link>https://1.1.1.1/1</link><guid>1</guid><pubDate>Mon, 01 Jan 2024 12:00:00 +0000</pubDate></item>
  <item><title>Old</title><link>https://1.1.1.1/2</link><guid>2</guid><pubDate>Sat, 01 Feb 2025 12:00:00 +0000</pubDate></item>
</channel></rss>
XML;

const FETCH_RSS_MIXED = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"><channel>
  <item><title>Older</title><link>https://1.1.1.1/a</link><guid>a</guid><pubDate>Sat, 01 Feb 2025 12:00:00 +0000</pubDate></item>
  <item><title>Newer1</title><link>https://1.1.1.1/b</link><guid>b</guid><pubDate>Sun, 01 Jun 2025 12:00:00 +0000</pubDate></item>
  <item><title>Newer2</title><link>https://1.1.1.1/c</link><guid>c</guid><pubDate>Mon, 15 Jun 2025 12:00:00 +0000</pubDate></item>
</channel></rss>
XML;

const FETCH_RSS_THREE_NEW = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"><channel>
  <item><title>New1</title><link>https://1.1.1.1/x</link><guid>x</guid><pubDate>Sun, 01 Jun 2025 12:00:00 +0000</pubDate></item>
  <item><title>New2</title><link>https://1.1.1.1/y</link><guid>y</guid><pubDate>Mon, 02 Jun 2025 12:00:00 +0000</pubDate></item>
  <item><title>New3</title><link>https://1.1.1.1/z</link><guid>z</guid><pubDate>Tue, 03 Jun 2025 12:00:00 +0000</pubDate></item>
</channel></rss>
XML;

beforeEach(fn () => Bus::fake());

it('first execution dispatches nothing and stores watermark from newest item', function () {
    Carbon::setTestNow('2026-01-15 10:00:00');
    Http::fake(['1.1.1.1/*' => Http::response(FETCH_RSS_OLD, 200)]);

    $automation = Automation::factory()->active()->create([
        'nodes' => [
            ['id' => 'trigger_1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['trigger_type' => 'schedule']],
            ['id' => 'fetch_1', 'type' => 'fetch_rss', 'position' => ['x' => 200, 'y' => 0], 'data' => ['feed_url' => 'https://1.1.1.1/feed']],
            ['id' => 'end_1', 'type' => 'end', 'position' => ['x' => 400, 'y' => 0], 'data' => []],
        ],
        'connections' => [
            ['id' => 'e1', 'source' => 'trigger_1', 'target' => 'fetch_1'],
            ['id' => 'e2', 'source' => 'fetch_1', 'target' => 'end_1'],
        ],
    ]);

    $run = AutomationRun::factory()->for($automation)->create(['current_node_id' => 'fetch_1']);

    $result = app(RunFetchRssNode::class)($run, ['feed_url' => 'https://1.1.1.1/feed']);

    expect($result->status)->toBe(NodeRunStatus::Completed);
    expect($result->nextHandle)->toBe('no_items');

    $state = AutomationNodeState::where('automation_id', $automation->id)->where('node_id', 'fetch_1')->first();
    expect($state)->not->toBeNull();
    expect(CarbonImmutable::parse($state->data['last_item_date'])->toIso8601String())
        ->toBe(CarbonImmutable::parse('2025-02-01 12:00:00')->toIso8601String());
});

it('processes the first new item on the current run and spawns siblings for the rest', function () {
    Carbon::setTestNow('2026-01-15 10:00:00');
    Http::fake(['1.1.1.1/*' => Http::response(FETCH_RSS_MIXED, 200)]);

    $automation = Automation::factory()->active()->create([
        'nodes' => [
            ['id' => 'trigger_1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['trigger_type' => 'schedule']],
            ['id' => 'fetch_1', 'type' => 'fetch_rss', 'position' => ['x' => 200, 'y' => 0], 'data' => ['feed_url' => 'https://1.1.1.1/feed']],
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

    $result = app(RunFetchRssNode::class)($run, ['feed_url' => 'https://1.1.1.1/feed']);

    expect($result->status)->toBe(NodeRunStatus::Completed);
    expect($result->output['fetched']['key'])->toBe('b'); // oldest of the new items
    expect($result->output['fetch']['count'])->toBe(2);
    expect($result->output['fetch']['spawned'])->toBe(1);

    // One sibling run created + dispatched at end_1
    expect(AutomationRun::where('automation_id', $automation->id)->count())->toBe(2);
    Bus::assertDispatchedTimes(ProcessAutomationNode::class, 1);
});

it('spawns sibling runs down the item edge for each remaining new item', function () {
    Carbon::setTestNow('2026-01-15 10:00:00');
    Http::fake(['1.1.1.1/*' => Http::response(FETCH_RSS_THREE_NEW, 200)]);

    $automation = Automation::factory()->active()->create([
        'nodes' => [
            ['id' => 'trigger_1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['trigger_type' => 'schedule']],
            ['id' => 'fetch_1', 'type' => 'fetch_rss', 'position' => ['x' => 200, 'y' => 0], 'data' => ['feed_url' => 'https://1.1.1.1/feed']],
            ['id' => 'generate_1', 'type' => 'generate', 'position' => ['x' => 400, 'y' => 0], 'data' => []],
        ],
        'connections' => [
            ['id' => 'e1', 'source' => 'trigger_1', 'target' => 'fetch_1'],
            ['id' => 'e2', 'source' => 'fetch_1', 'source_handle' => 'default', 'target' => 'generate_1'],
        ],
    ]);

    // Watermark predates all three items, so every item is "new".
    AutomationNodeState::create([
        'automation_id' => $automation->id,
        'node_id' => 'fetch_1',
        'data' => ['last_item_date' => '2025-01-01T00:00:00+00:00'],
    ]);

    $run = AutomationRun::factory()->for($automation)->create(['current_node_id' => 'fetch_1']);

    $result = app(RunFetchRssNode::class)($run, ['feed_url' => 'https://1.1.1.1/feed']);

    // Current run handles item #1 (oldest); siblings handle #2 and #3.
    expect($result->status)->toBe(NodeRunStatus::Completed);
    expect($result->output['fetched']['key'])->toBe('x');
    expect($result->output['fetch']['count'])->toBe(3);
    expect($result->output['fetch']['spawned'])->toBe(2);

    Bus::assertDispatchedTimes(ProcessAutomationNode::class, 2);
    Bus::assertDispatched(
        ProcessAutomationNode::class,
        fn (ProcessAutomationNode $job) => $job->nodeId === 'generate_1',
    );
});

it('does not persist the production watermark on a manual real-data test', function () {
    Carbon::setTestNow('2026-01-15 10:00:00');
    Http::fake(['1.1.1.1/*' => Http::response(FETCH_RSS_MIXED, 200)]);

    $automation = Automation::factory()->active()->create([
        'nodes' => [
            ['id' => 'trigger_1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['trigger_type' => 'schedule']],
            ['id' => 'fetch_1', 'type' => 'fetch_rss', 'position' => ['x' => 200, 'y' => 0], 'data' => ['feed_url' => 'https://1.1.1.1/feed']],
            ['id' => 'end_1', 'type' => 'end', 'position' => ['x' => 400, 'y' => 0], 'data' => []],
        ],
        'connections' => [
            ['id' => 'e1', 'source' => 'trigger_1', 'target' => 'fetch_1'],
            ['id' => 'e2', 'source' => 'fetch_1', 'target' => 'end_1'],
        ],
    ]);

    AutomationNodeState::create([
        'automation_id' => $automation->id,
        'node_id' => 'fetch_1',
        'data' => ['last_item_date' => '2025-01-01T00:00:00+00:00'],
    ]);

    $run = AutomationRun::factory()->for($automation)->create([
        'current_node_id' => 'fetch_1',
        'is_manual' => true,
        'is_dry_run' => false,
    ]);

    $result = app(RunFetchRssNode::class)($run, ['feed_url' => 'https://1.1.1.1/feed']);

    // Reads the production watermark, so it finds the newer items.
    expect($result->status)->toBe(NodeRunStatus::Completed);
    expect($result->output['fetch']['count'])->toBeGreaterThan(0);

    // The persisted watermark stays put — a second identical manual test would
    // surface the same items.
    $state = AutomationNodeState::for($automation->id, 'fetch_1');
    expect($state->data['last_item_date'])->toBe('2025-01-01T00:00:00+00:00');
});

it('advances the production watermark on a non-manual real-data run', function () {
    Carbon::setTestNow('2026-01-15 10:00:00');
    Http::fake(['1.1.1.1/*' => Http::response(FETCH_RSS_MIXED, 200)]);

    $automation = Automation::factory()->active()->create([
        'nodes' => [
            ['id' => 'trigger_1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['trigger_type' => 'schedule']],
            ['id' => 'fetch_1', 'type' => 'fetch_rss', 'position' => ['x' => 200, 'y' => 0], 'data' => ['feed_url' => 'https://1.1.1.1/feed']],
            ['id' => 'end_1', 'type' => 'end', 'position' => ['x' => 400, 'y' => 0], 'data' => []],
        ],
        'connections' => [
            ['id' => 'e1', 'source' => 'trigger_1', 'target' => 'fetch_1'],
            ['id' => 'e2', 'source' => 'fetch_1', 'target' => 'end_1'],
        ],
    ]);

    AutomationNodeState::create([
        'automation_id' => $automation->id,
        'node_id' => 'fetch_1',
        'data' => ['last_item_date' => '2025-01-01T00:00:00+00:00'],
    ]);

    $run = AutomationRun::factory()->for($automation)->create([
        'current_node_id' => 'fetch_1',
        'is_manual' => false,
        'is_dry_run' => false,
    ]);

    app(RunFetchRssNode::class)($run, ['feed_url' => 'https://1.1.1.1/feed']);

    $state = AutomationNodeState::for($automation->id, 'fetch_1');
    expect(CarbonImmutable::parse($state->data['last_item_date'])->toIso8601String())
        ->toBe(CarbonImmutable::parse('Mon, 15 Jun 2025 12:00:00 +0000')->toIso8601String());
});

it('fails when feed_url is missing', function () {
    $automation = Automation::factory()->active()->create();
    $run = AutomationRun::factory()->for($automation)->create(['current_node_id' => 'fetch_1']);

    $result = app(RunFetchRssNode::class)($run, []);

    expect($result->status)->toBe(NodeRunStatus::Failed);
});
