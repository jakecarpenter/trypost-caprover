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
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Generalized HTTP node — supersedes the old `fetch_json` polling-only node.
 *
 * Two execution modes share one config:
 *  - **Single request mode** (`items_path` empty): one HTTP call, the parsed
 *    response is set on `context.fetched` as a single payload. Useful for
 *    enrichment ("look up user X before generating") or webhook-style fan-out.
 *  - **Polling mode** (`items_path` set): same as the old fetch — extract an
 *    items array, filter by watermark (when `item_date_path` is provided),
 *    process the oldest unseen item in the current run and spawn siblings for
 *    the remainder. Each item ends up driving its own downstream branch.
 *
 * Auth types: none, bearer, basic, api_key. Credentials are stored encrypted
 * on the Automation model (see `Automation::booted()`) and decrypted here.
 */
class RunHttpRequestNode
{
    private const ITEM_HANDLE = 'default';

    public function __construct(
        private ExpressionResolver $resolver,
        private SafeHttpFetcher $safeHttp,
    ) {}

    public function __invoke(AutomationRun $run, array $config): NodeRunResult
    {
        $url = (string) data_get($config, 'url', '');
        $method = strtoupper((string) data_get($config, 'method', 'GET'));
        $itemsPath = data_get($config, 'items_path');
        $itemKeyPath = data_get($config, 'item_key_path');
        $itemDatePath = data_get($config, 'item_date_path');
        $nodeId = (string) $run->current_node_id;
        $context = $run->resolverContext();

        if ($url === '') {
            return NodeRunResult::failed('HTTP request node missing url.');
        }

        $resolvedUrl = $this->resolver->resolve($url, $context);

        try {
            $this->safeHttp->guardAgainstSsrf($resolvedUrl);
        } catch (RuntimeException) {
            return NodeRunResult::failed(__('automations.errors.url_not_allowed'), [
                'reason' => 'url_not_allowed',
                'url' => $resolvedUrl,
            ]);
        }

        $request = $this->buildRequest($config, $context);
        $body = $this->buildJsonBody($method, $config, $context);

        try {
            $response = match ($method) {
                'GET' => $request->get($resolvedUrl),
                'DELETE' => $request->delete($resolvedUrl),
                'POST' => $request->post($resolvedUrl, $body),
                'PUT' => $request->put($resolvedUrl, $body),
                'PATCH' => $request->patch($resolvedUrl, $body),
                default => null,
            };
        } catch (Throwable $e) {
            return NodeRunResult::failed('HTTP request threw an exception.', ['message' => $e->getMessage()]);
        }

        if ($response === null) {
            return NodeRunResult::failed("Unsupported HTTP method: {$method}");
        }

        if (! $response->successful()) {
            return NodeRunResult::failed('HTTP request failed.', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500),
            ]);
        }

        $body = $response->json();
        $useItems = is_string($itemsPath) && $itemsPath !== '';

        if (! $useItems) {
            // Single-response mode: pass the whole body forward as `fetched`.
            return NodeRunResult::completed([
                'fetch' => ['count' => 1, 'spawned' => 0],
                'fetched' => $body,
            ]);
        }

        $rawItems = data_get($body, $itemsPath, []);
        if (! is_array($rawItems)) {
            return NodeRunResult::failed('Items path did not resolve to an array.');
        }

        // Dry runs bypass the watermark entirely: they neither load nor advance
        // the persisted state, AND they process every item so the user actually
        // sees data flow (with a "now" watermark, fresh tests would yield 0
        // items on any feed older than today and look broken).
        $useWatermark = is_string($itemDatePath) && $itemDatePath !== '' && ! $run->is_dry_run;
        $state = $useWatermark ? AutomationNodeState::for($run->automation_id, $nodeId) : null;
        $watermark = $useWatermark
            ? $this->parseWatermark(data_get($state->data, 'last_item_date'))
            : null;
        $newestSeen = null;

        $newItems = [];

        foreach ($rawItems as $item) {
            if (! is_array($item)) {
                continue;
            }

            if ($useWatermark) {
                $itemDate = $this->parseDate(data_get($item, $itemDatePath));
                if ($itemDate === null) {
                    continue;
                }
                if ($newestSeen === null || $itemDate->greaterThan($newestSeen)) {
                    $newestSeen = $itemDate;
                }
                if (! $itemDate->greaterThan($watermark)) {
                    continue;
                }
            }

            $key = $itemKeyPath ? data_get($item, $itemKeyPath) : null;
            $key = ($key === null || $key === '') ? hash('sha256', json_encode($item)) : (string) $key;

            $newItems[] = array_merge($item, ['_key' => $key]);
        }

        if ($useWatermark && $newestSeen !== null && $state !== null && ! $run->is_manual) {
            $state->update(['data' => array_merge($state->data ?? [], [
                'last_item_date' => $newestSeen->toIso8601String(),
            ])]);
        }

        if ($newItems === []) {
            return NodeRunResult::completed(['fetch' => ['count' => 0]], nextHandle: 'no_items');
        }

        $first = array_shift($newItems);

        // Dry runs skip sibling spawning so the test stays a single in-memory
        // walk through one item — see TestAutomation's dry-run contract.
        if (! $run->is_dry_run) {
            $this->spawnSiblings($run, $nodeId, $newItems);
        }

        return NodeRunResult::completed([
            'fetch' => ['count' => count($newItems) + 1, 'spawned' => $run->is_dry_run ? 0 : count($newItems)],
            'fetched' => $first,
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     */
    private function buildRequest(array $config, array $context): PendingRequest
    {
        $request = Http::asJson();

        $headers = [];
        foreach ((array) data_get($config, 'headers', []) as $k => $v) {
            $headers[$k] = $this->resolver->resolve((string) $v, $context);
        }

        $authType = data_get($config, 'auth_type', 'none');
        if ($authType === 'bearer') {
            $token = $this->decrypt((string) data_get($config, 'auth_token', ''));
            if ($token !== '') {
                $request = $request->withToken($this->resolver->resolve($token, $context));
            }
        } elseif ($authType === 'basic') {
            $user = (string) data_get($config, 'auth_username', '');
            $pass = $this->decrypt((string) data_get($config, 'auth_password', ''));
            if ($user !== '' || $pass !== '') {
                $request = $request->withBasicAuth(
                    $this->resolver->resolve($user, $context),
                    $this->resolver->resolve($pass, $context),
                );
            }
        } elseif ($authType === 'api_key') {
            $headerName = (string) data_get($config, 'auth_header_name', 'X-API-Key');
            $token = $this->decrypt((string) data_get($config, 'auth_token', ''));
            if ($token !== '') {
                $headers[$headerName] = $this->resolver->resolve($token, $context);
            }
        }

        if ($headers !== []) {
            $request = $request->withHeaders($headers);
        }

        return $request->withUserAgent(config('trypost.user_agent'));
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function buildJsonBody(string $method, array $config, array $context): array
    {
        if (! in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            return [];
        }

        $template = (string) data_get($config, 'body_template', '');
        if ($template === '') {
            return [];
        }

        $rendered = $this->resolver->resolve($template, $context);
        $decoded = json_decode($rendered, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
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

    private function decrypt(string $value): string
    {
        if ($value === '') {
            return '';
        }

        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            // Value isn't an encrypted payload (legacy plain text or already decrypted).
            return $value;
        }
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

    private function parseDate(mixed $raw): ?CarbonImmutable
    {
        if (! is_string($raw) && ! is_numeric($raw)) {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $raw);
        } catch (Throwable) {
            return null;
        }
    }
}
