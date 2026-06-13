/**
 * Output handle ids of a Condition node. Must mirror the backend
 * App\Enums\Automation\Condition\Handle enum — the id rendered here becomes the
 * edge `source_handle`, which the run engine matches to decide the branch.
 */
export const ConditionHandle = {
    Yes: 'yes',
    No: 'no',
} as const;

export type ConditionHandleValue = (typeof ConditionHandle)[keyof typeof ConditionHandle];
