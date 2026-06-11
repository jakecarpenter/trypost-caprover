<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Automations;

use App\Enums\Automation\Condition\Operator as ConditionOperator;
use App\Enums\Automation\Node\Type as NodeType;
use App\Enums\Automation\Publish\Mode as PublishMode;
use App\Enums\Automation\Trigger\Type as TriggerType;
use App\Services\Automation\GenerateNodeValidator;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAutomationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['sometimes', 'string', 'max:120'],
            'nodes' => ['sometimes', 'array'],
            'nodes.*.id' => ['required', 'string'],
            'nodes.*.type' => ['required', 'string', Rule::in(array_column(NodeType::cases(), 'value'))],
            'nodes.*.position' => ['required', 'array'],
            'nodes.*.position.x' => ['required', 'numeric'],
            'nodes.*.position.y' => ['required', 'numeric'],
            'nodes.*.data' => ['required', 'array'],
            'connections' => ['sometimes', 'array'],
            'connections.*.id' => ['required', 'string'],
            'connections.*.source' => ['required', 'string'],
            'connections.*.target' => ['required', 'string'],
            'connections.*.source_handle' => ['nullable', 'string'],
            'connections.*.target_handle' => ['nullable', 'string'],
            'variables' => ['sometimes', 'array', 'max:50'],
            'variables.*.key' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/', 'distinct'],
            'variables.*.value' => ['nullable', 'string'],
        ];

        // Per-node data validation. We build these dynamically so each node's
        // type drives the shape of its `data` payload, and so errors come back
        // with full paths like `nodes.2.data.feed_url` for the frontend to map.
        $nodes = $this->input('nodes', []);
        if (is_array($nodes)) {
            foreach ($nodes as $i => $node) {
                $type = data_get($node, 'type');
                foreach ($this->dataRulesForNodeType($type) as $field => $fieldRules) {
                    $rules["nodes.{$i}.data.{$field}"] = $fieldRules;
                }
            }
        }

        return $rules;
    }

    /**
     * Block saving a Generate node whose intended image count doesn't fit a
     * selected account's content-type (mirrors the inline frontend validation),
     * keyed so the frontend surfaces it under that node's accounts field.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $nodes = $this->input('nodes', []);

            if (! is_array($nodes)) {
                return;
            }

            $generateValidator = app(GenerateNodeValidator::class);

            foreach ($nodes as $i => $node) {
                if (data_get($node, 'type') !== NodeType::Generate->value) {
                    continue;
                }

                $issue = $generateValidator->issueFor((array) data_get($node, 'data', []));

                if ($issue !== null) {
                    $validator->errors()->add("nodes.{$i}.data.accounts", $issue);
                }
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'nodes.*.data.feed_url' => 'Feed URL',
            'nodes.*.data.url' => 'URL',
            'nodes.*.data.cron' => 'cron expression',
            'nodes.*.data.duration' => 'duration',
            'nodes.*.data.unit' => 'unit',
            'nodes.*.data.field' => 'field',
            'nodes.*.data.operator' => 'operator',
            'nodes.*.data.mode' => 'mode',
            'nodes.*.data.method' => 'method',
            'nodes.*.data.trigger_type' => 'trigger type',
            'nodes.*.data.prompt_template' => 'prompt template',
            'nodes.*.data.accounts' => 'accounts',
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function dataRulesForNodeType(?string $type): array
    {
        return match ($type) {
            NodeType::Trigger->value => [
                'trigger_type' => ['required', Rule::in(array_column(TriggerType::cases(), 'value'))],
                'cron' => ['required_if:nodes.*.data.trigger_type,'.TriggerType::Schedule->value, 'string'],
            ],
            NodeType::FetchRss->value => [
                'feed_url' => ['required', 'url'],
            ],
            NodeType::HttpRequest->value => [
                'url' => ['required', 'url'],
                'method' => ['required', Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])],
                'auth_type' => ['required', Rule::in(['none', 'bearer', 'basic', 'api_key'])],
                'auth_token' => ['nullable', 'string'],
                'auth_username' => ['nullable', 'string'],
                'auth_password' => ['nullable', 'string'],
                'auth_header_name' => ['nullable', 'string'],
                'body_template' => ['nullable', 'string'],
                'headers' => ['nullable', 'array'],
                'headers.*' => ['string'],
                'items_path' => ['nullable', 'string'],
                'item_key_path' => ['nullable', 'string'],
                'item_date_path' => ['nullable', 'string'],
            ],
            NodeType::Generate->value => [
                'accounts' => ['required', 'array', 'min:1'],
                'prompt_template' => ['required', 'string'],
                'target_slide_count' => ['nullable', 'integer', 'min:1', 'max:'.GenerateNodeValidator::MAX_GENERATED_IMAGES],
            ],
            NodeType::Delay->value => [
                'duration' => ['required', 'integer', 'min:1'],
                'unit' => ['required', Rule::in(['minutes', 'hours', 'days'])],
            ],
            NodeType::Condition->value => [
                'field' => ['required', 'string'],
                'operator' => ['required', Rule::in(array_column(ConditionOperator::cases(), 'value'))],
                'value' => ['nullable', 'string'],
            ],
            NodeType::Publish->value => [
                'mode' => ['required', Rule::in(array_column(PublishMode::cases(), 'value'))],
                'scheduled_offset' => ['nullable', 'integer', 'min:0'],
            ],
            NodeType::Webhook->value => [
                'url' => ['required', 'url'],
                'method' => ['required', Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])],
                'payload_template' => ['nullable', 'string'],
                'headers' => ['nullable', 'array'],
                'headers.*' => ['string'],
            ],
            NodeType::End->value => [
                'reason' => ['nullable', 'string'],
            ],
            default => [],
        };
    }
}
