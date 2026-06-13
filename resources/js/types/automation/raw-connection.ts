/**
 * Edge as serialized by the backend. Handle ids arrive snake_cased; the
 * frontend normalizes them to camelCase before feeding Vue Flow.
 */
export interface RawConnection {
    id: string;
    source: string;
    target: string;
    source_handle?: string | null;
    sourceHandle?: string | null;
    target_handle?: string | null;
    targetHandle?: string | null;
}
