<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Automation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AutomationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'name' => $this->name,
            'status' => $this->status->value,
            'nodes' => $this->maskSensitiveNodeFields($this->nodes ?? []),
            'connections' => $this->connections ?? [],
            'variables' => $this->maskVariables($this->variables ?? []),
            'activated_at' => $this->activated_at,
            'paused_at' => $this->paused_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Replace any stored credentials with a placeholder before they leave
     * the server. The frontend treats the placeholder as "keep current" on
     * save (see Automation::booted()), so editing other fields doesn't wipe
     * the stored secret.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array<string, mixed>>
     */
    private function maskSensitiveNodeFields(array $nodes): array
    {
        foreach ($nodes as &$node) {
            foreach (Automation::SENSITIVE_NODE_FIELDS as $field) {
                if (data_get($node, "data.{$field}") !== null && data_get($node, "data.{$field}") !== '') {
                    data_set($node, "data.{$field}", Automation::SENSITIVE_PLACEHOLDER);
                }
            }
        }

        return $nodes;
    }

    /**
     * Replace stored variable values with the placeholder so secrets never
     * leave the server. The frontend references variables by key
     * (`{{ variables.KEY }}`), so the masked value doesn't hinder reuse, and
     * re-saving the placeholder keeps the stored ciphertext.
     *
     * @param  array<int, array<string, mixed>>  $variables
     * @return array<int, array<string, mixed>>
     */
    private function maskVariables(array $variables): array
    {
        foreach ($variables as &$variable) {
            if (data_get($variable, 'value') !== null && data_get($variable, 'value') !== '') {
                $variable['value'] = Automation::SENSITIVE_PLACEHOLDER;
            }
        }

        return $variables;
    }
}
