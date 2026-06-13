<?php

declare(strict_types=1);

namespace App\Actions\Automation\Automation;

use App\Models\Automation;
use App\Models\AutomationRun;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;

class GetAutomationInvocations
{
    /**
     * Paginated, real (non-dry-run) executions for the Invocations tab, newest
     * first, each carrying the count of node runs it produced so the list can
     * render a "Workflow completed · N steps" summary without N+1 queries.
     *
     * @return LengthAwarePaginator<int, AutomationRun>
     */
    public function __invoke(Automation $automation, ?string $status = null, ?string $search = null): LengthAwarePaginator|CursorPaginator
    {
        return $automation->runs()
            ->productionRuns()
            ->withCount('nodeRuns')
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($search !== null && $search !== '', fn ($query) => $query->whereLike('id', "%{$search}%"))
            ->latest()
            ->paginate((int) config('app.pagination.default'));
    }
}
