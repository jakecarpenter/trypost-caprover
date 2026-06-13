export interface Run {
    id: string;
    status: string;
    current_node_id: string | null;
    started_at: string | null;
    finished_at: string | null;
    error: { message?: string } | null;
    is_dry_run: boolean;
}
