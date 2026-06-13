/**
 * Comparison operators for the Condition node. Mirrors the backend
 * App\Enums\Automation\Condition\Operator enum.
 */
export const ConditionOperator = {
    Contains: 'contains',
    NotContains: 'not_contains',
    Equals: 'equals',
    NotEquals: 'not_equals',
    Matches: 'matches',
    GreaterThan: 'greater_than',
    LessThan: 'less_than',
} as const;

export type ConditionOperatorValue = (typeof ConditionOperator)[keyof typeof ConditionOperator];
