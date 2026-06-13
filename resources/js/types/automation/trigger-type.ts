export const TriggerType = {
    Schedule: 'schedule',
    PostPublished: 'post_published',
    PostScheduled: 'post_scheduled',
} as const;

export type TriggerTypeValue = (typeof TriggerType)[keyof typeof TriggerType];
