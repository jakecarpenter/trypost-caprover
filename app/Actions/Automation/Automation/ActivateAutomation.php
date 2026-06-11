<?php

declare(strict_types=1);

namespace App\Actions\Automation\Automation;

use App\Enums\Automation\Node\Type as NodeType;
use App\Enums\Automation\Status;
use App\Models\Automation;
use App\Services\Automation\GenerateNodeValidator;

class ActivateAutomation
{
    public function __construct(private GenerateNodeValidator $generateValidator) {}

    public function __invoke(Automation $automation): Automation
    {
        $this->validate($automation);

        $automation->update([
            'status' => Status::Active,
            'activated_at' => now(),
            'paused_at' => null,
        ]);

        return $automation;
    }

    private function validate(Automation $automation): void
    {
        $nodes = $automation->nodes ?? [];
        $connections = $automation->connections ?? [];

        $triggers = collect($nodes)->where('type', 'trigger');
        if ($triggers->count() !== 1) {
            throw new \DomainException(__('automations.errors.must_have_one_trigger'));
        }

        $trigger = $triggers->first();
        $hasTargetFromTrigger = collect($connections)->contains('source', $trigger['id']);
        if (! $hasTargetFromTrigger) {
            throw new \DomainException(__('automations.errors.trigger_must_be_connected'));
        }

        foreach ($nodes as $node) {
            if (data_get($node, 'type') !== NodeType::Generate->value) {
                continue;
            }

            $issue = $this->generateValidator->issueFor((array) data_get($node, 'data', []));

            if ($issue !== null) {
                throw new \DomainException($issue);
            }
        }
    }
}
