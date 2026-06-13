/**
 * Publish modes for the Publish node. Mirrors the backend
 * App\Enums\Automation\Publish\Mode enum.
 */
export const PublishMode = {
    Now: 'now',
    Scheduled: 'scheduled',
    Draft: 'draft',
} as const;

export type PublishModeValue = (typeof PublishMode)[keyof typeof PublishMode];
