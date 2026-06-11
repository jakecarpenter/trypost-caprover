<?php

declare(strict_types=1);

namespace App\Actions\Automation\Node;

use App\DataTransferObjects\Automation\NodeRunResult;
use App\Enums\Automation\Run\Status as RunStatus;
use App\Jobs\Automation\ProcessAutomationNode;
use App\Models\AutomationNodeState;
use App\Models\AutomationRun;
use App\Services\Automation\ExpressionResolver;
use App\Services\Brand\SafeHttpFetcher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

/**
 * Fetches an RSS feed and progresses the run with the next-up unseen item.
 *
 *  - Filters by a per-node watermark stored in `automation_node_states`, so the
 *    very first run on an old feed processes none of the historical items.
 *  - When the fetch returns N new items, the current run takes item[0]; the
 *    remaining N-1 items are spawned as sibling runs that resume at the node
 *    immediately after this Fetch (with `context.fetched` already populated),
 *    so each item ends up generating its own Post / Webhook / etc.
 *  - When the feed yields no new items, the result short-circuits via the
 *    `no_items` handle; if the user hasn't wired anything to it, the run
 *    completes silently (handled by AdvanceAutomationRun's default branch).
 */
class RunFetchRssNode
{
    private const ITEM_HANDLE = 'default';

    public function __construct(
        private ExpressionResolver $resolver,
        private SafeHttpFetcher $safeHttp,
    ) {}

    public function __invoke(AutomationRun $run, array $config): NodeRunResult
    {
        $feedUrl = $this->resolver->resolve((string) data_get($config, 'feed_url', ''), $run->resolverContext());

        if ($feedUrl === '') {
            return NodeRunResult::failed('Fetch RSS node missing feed_url.');
        }

        try {
            $this->safeHttp->guardAgainstSsrf($feedUrl);
        } catch (RuntimeException) {
            return NodeRunResult::failed(__('automations.errors.url_not_allowed'), [
                'reason' => 'url_not_allowed',
                'url' => $feedUrl,
            ]);
        }

        $response = Http::get($feedUrl);

        if (! $response->successful()) {
            return NodeRunResult::failed('Feed request failed.', ['status' => $response->status()]);
        }

        try {
            $xml = new SimpleXMLElement($response->body());
        } catch (Throwable $e) {
            Log::warning('Fetch RSS node: malformed feed', [
                'run_id' => $run->id,
                'feed_url' => $feedUrl,
                'error' => $e->getMessage(),
            ]);

            return NodeRunResult::failed('Malformed RSS feed.');
        }

        $nodeId = (string) $run->current_node_id;
        // Dry runs bypass the watermark entirely: they neither load nor advance
        // the persisted state, AND they use an epoch watermark so every item in
        // the feed is treated as new — otherwise a "now" default would silently
        // return zero items on any feed that hasn't published in the last second.
        $state = $run->is_dry_run ? null : AutomationNodeState::for($run->automation_id, $nodeId);
        $watermark = $run->is_dry_run
            ? CarbonImmutable::createFromTimestamp(0)
            : $this->parseWatermark(data_get($state->data, 'last_item_date'));

        [$newItems, $newestSeen] = $this->collectNewItems($xml, $watermark);

        if ($state !== null && $newestSeen !== null && ! $run->is_manual) {
            $state->update(['data' => array_merge($state->data ?? [], [
                'last_item_date' => $newestSeen->toIso8601String(),
            ])]);
        }

        if ($newItems === []) {
            return NodeRunResult::completed(['fetch' => ['count' => 0]], nextHandle: 'no_items');
        }

        $first = array_shift($newItems);

        if (! $run->is_dry_run) {
            $this->spawnSiblings($run, $nodeId, $newItems);
        }

        return NodeRunResult::completed([
            'fetch' => ['count' => count($newItems) + 1, 'spawned' => $run->is_dry_run ? 0 : count($newItems)],
            'fetched' => $first,
        ]);
    }

    private function collectNewItems(SimpleXMLElement $xml, CarbonImmutable $watermark): array
    {
        $items = [];
        $newestSeen = null;

        foreach ($xml->channel->item ?? [] as $item) {
            $key = (string) ($item->guid ?? $item->link);
            if ($key === '') {
                continue;
            }

            $pubDate = $this->parsePubDate((string) $item->pubDate);
            if ($pubDate === null) {
                continue;
            }

            if ($newestSeen === null || $pubDate->greaterThan($newestSeen)) {
                $newestSeen = $pubDate;
            }

            if (! $pubDate->greaterThan($watermark)) {
                continue;
            }

            $items[] = [
                '_pubDate' => $pubDate,
                'key' => $key,
                'title' => (string) $item->title,
                'link' => (string) $item->link,
                'description' => (string) $item->description,
                'pubDate' => (string) $item->pubDate,
            ];
        }

        // Process oldest-first so siblings inherit a stable order matching feed chronology.
        usort($items, fn ($a, $b) => $a['_pubDate']->getTimestamp() <=> $b['_pubDate']->getTimestamp());

        // Drop the internal sort key — downstream nodes shouldn't see it.
        $items = array_map(function (array $item): array {
            unset($item['_pubDate']);

            return $item;
        }, $items);

        return [$items, $newestSeen];
    }

    private function spawnSiblings(AutomationRun $parent, string $fetchNodeId, array $items): void
    {
        if ($items === []) {
            return;
        }

        $nextNodeId = $this->findNextNodeId($parent, $fetchNodeId);

        foreach ($items as $item) {
            $sibling = AutomationRun::create([
                'automation_id' => $parent->automation_id,
                'is_manual' => $parent->is_manual,
                'is_dry_run' => $parent->is_dry_run,
                'status' => RunStatus::Pending,
                'context' => array_merge($parent->context ?? [], ['fetched' => $item]),
            ]);

            if ($nextNodeId === null) {
                $sibling->update(['status' => RunStatus::Completed, 'finished_at' => now()]);

                continue;
            }

            ProcessAutomationNode::dispatch($sibling, $nextNodeId);
        }
    }

    private function findNextNodeId(AutomationRun $run, string $fromNodeId): ?string
    {
        $connection = collect($run->automation->connections ?? [])
            ->first(fn ($c) => $c['source'] === $fromNodeId && ($c['source_handle'] ?? self::ITEM_HANDLE) === self::ITEM_HANDLE);

        return $connection['target'] ?? null;
    }

    private function parseWatermark(?string $stored): CarbonImmutable
    {
        if ($stored === null) {
            return CarbonImmutable::now();
        }

        try {
            return CarbonImmutable::parse($stored);
        } catch (Throwable) {
            return CarbonImmutable::now();
        }
    }

    private function parsePubDate(string $raw): ?CarbonImmutable
    {
        if ($raw === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($raw);
        } catch (Throwable) {
            return null;
        }
    }
}
