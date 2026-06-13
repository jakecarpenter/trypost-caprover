export const NodeRunStatus = {
    Running: 'running',
    Completed: 'completed',
    Failed: 'failed',
    Skipped: 'skipped',
} as const;

export type NodeRunStatusValue = (typeof NodeRunStatus)[keyof typeof NodeRunStatus];
