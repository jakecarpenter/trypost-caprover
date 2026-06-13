import type { Node } from '@vue-flow/core';

import type { RawConnection } from './raw-connection';

export interface AutomationVariable {
    key: string;
    value: string;
}

/**
 * Domain shape of an Automation as it flows between the Inertia backend and
 * the Vue pages. Many fields are optional because different pages hydrate
 * different subsets (Index needs `created_at`, Show needs `activated_at`,
 * Form needs `nodes` / `connections`).
 */
export interface Automation {
    id: string;
    name: string;
    status: string;
    nodes?: Node[];
    connections?: RawConnection[];
    variables?: AutomationVariable[];
    activated_at?: string | null;
    paused_at?: string | null;
    created_at?: string;
}
