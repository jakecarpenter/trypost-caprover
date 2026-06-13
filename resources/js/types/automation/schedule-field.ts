export const ScheduleField = {
    Minutes: 'minutes',
    Hours: 'hours',
    Days: 'days',
    Weeks: 'weeks',
    Months: 'months',
} as const;

export type ScheduleFieldValue = (typeof ScheduleField)[keyof typeof ScheduleField];
