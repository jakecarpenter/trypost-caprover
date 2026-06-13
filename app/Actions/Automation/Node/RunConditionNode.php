<?php

declare(strict_types=1);

namespace App\Actions\Automation\Node;

use App\DataTransferObjects\Automation\NodeRunResult;
use App\Enums\Automation\Condition\Handle;
use App\Enums\Automation\Condition\Operator;
use App\Models\AutomationRun;
use App\Services\Automation\ExpressionResolver;
use Throwable;

class RunConditionNode
{
    private const MAX_REGEX_LENGTH = 200;

    public function __construct(private ExpressionResolver $resolver) {}

    public function __invoke(AutomationRun $run, array $config): NodeRunResult
    {
        $context = $run->resolverContext();
        $field = $this->resolver->resolve((string) data_get($config, 'field', ''), $context);
        $operator = Operator::from(data_get($config, 'operator', Operator::Equals->value));
        $value = $this->resolver->resolve((string) data_get($config, 'value', ''), $context);

        $matched = match ($operator) {
            Operator::Contains => str_contains($field, $value),
            Operator::NotContains => ! str_contains($field, $value),
            Operator::Equals => $field === $value,
            Operator::NotEquals => $field !== $value,
            Operator::Matches => $this->safeRegexMatch($value, $field),
            Operator::GreaterThan => is_numeric($field) && is_numeric($value) && (float) $field > (float) $value,
            Operator::LessThan => is_numeric($field) && is_numeric($value) && (float) $field < (float) $value,
        };

        return NodeRunResult::completed(
            output: ['condition' => ['resolved_field' => $field, 'matched' => $matched]],
            nextHandle: ($matched ? Handle::Yes : Handle::No)->value,
        );
    }

    private function safeRegexMatch(string $pattern, string $subject): bool
    {
        if (strlen($pattern) > self::MAX_REGEX_LENGTH) {
            return false;
        }

        $escaped = str_replace('~', '\~', $pattern);
        $regex = "~{$escaped}~u";

        try {
            $result = @preg_match($regex, $subject);
        } catch (Throwable) {
            return false;
        }

        if ($result === false || preg_last_error() !== PREG_NO_ERROR) {
            return false;
        }

        return $result === 1;
    }
}
