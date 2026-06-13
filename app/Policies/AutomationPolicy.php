<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Automation;
use App\Models\User;

class AutomationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentWorkspace !== null;
    }

    public function view(User $user, Automation $automation): bool
    {
        return $automation->workspace_id === $user->current_workspace_id;
    }

    public function create(User $user): bool
    {
        return $user->currentWorkspace !== null;
    }

    public function update(User $user, Automation $automation): bool
    {
        return $automation->workspace_id === $user->current_workspace_id;
    }

    public function delete(User $user, Automation $automation): bool
    {
        return $automation->workspace_id === $user->current_workspace_id;
    }

    public function activate(User $user, Automation $automation): bool
    {
        return $this->update($user, $automation);
    }

    public function pause(User $user, Automation $automation): bool
    {
        return $this->update($user, $automation);
    }
}
