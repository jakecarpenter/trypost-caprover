import type { Run } from './run';

export interface TriggerItem {
    id: string;
    item_key: string;
    payload: Record<string, unknown>;
    first_seen_at: string;
    run: Run | null;
}
