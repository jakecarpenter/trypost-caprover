export const RunStatus = {
    Pending: 'pending',
    Running: 'running',
    Waiting: 'waiting',
    Completed: 'completed',
    Failed: 'failed',
    Cancelled: 'cancelled',
} as const;

export type RunStatusValue = (typeof RunStatus)[keyof typeof RunStatus];
