<?php

declare(strict_types=1);

namespace App\Actions\Automation\Automation;

use App\Models\Automation;
use DomainException;

class UpdateAutomation
{
    public function __invoke(Automation $automation, array $data): Automation
    {
        $this->detectCycles($data['nodes'] ?? [], $data['connections'] ?? []);

        $automation->update([
            'name' => $data['name'] ?? $automation->name,
            'nodes' => $data['nodes'] ?? $automation->nodes,
            'connections' => $data['connections'] ?? $automation->connections,
            'variables' => $data['variables'] ?? $automation->variables,
        ]);

        return $automation->fresh();
    }

    private function detectCycles(array $nodes, array $connections): void
    {
        $adj = [];
        foreach ($connections as $c) {
            $adj[$c['source']][] = $c['target'];
        }

        /** @var array<string, string> $state state: 'white' (unvisited), 'gray' (in stack), 'black' (done) */
        $state = [];
        foreach ($nodes as $node) {
            $state[$node['id']] = 'white';
        }

        foreach ($nodes as $node) {
            if ($state[$node['id']] === 'white' && $this->hasCycleFrom($node['id'], $adj, $state)) {
                throw new DomainException(__('automations.errors.graph_contains_cycle'));
            }
        }
    }

    private function hasCycleFrom(string $node, array $adj, array &$state): bool
    {
        $state[$node] = 'gray';

        foreach ($adj[$node] ?? [] as $next) {
            if (! isset($state[$next])) {
                continue;
            }
            if ($state[$next] === 'gray') {
                return true;
            }
            if ($state[$next] === 'white' && $this->hasCycleFrom($next, $adj, $state)) {
                return true;
            }
        }

        $state[$node] = 'black';

        return false;
    }
}
