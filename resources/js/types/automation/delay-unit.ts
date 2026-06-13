/**
 * Time units for the Delay node. Mirrors the backend
 * App\Enums\Automation\DelayUnit enum.
 */
export const DelayUnit = {
    Minutes: 'minutes',
    Hours: 'hours',
    Days: 'days',
} as const;

export type DelayUnitValue = (typeof DelayUnit)[keyof typeof DelayUnit];
