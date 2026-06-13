<?php

declare(strict_types=1);

namespace App\Actions\Automation\Automation;

use App\Models\Automation;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListAutomations
{
    public function __invoke(Workspace $workspace): LengthAwarePaginator
    {
        return Automation::query()
            ->where('workspace_id', $workspace->id)
            ->orderByDesc('created_at')
            ->paginate((int) config('app.pagination.default'));
    }
}
