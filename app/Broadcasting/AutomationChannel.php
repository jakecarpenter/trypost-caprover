<?php

declare(strict_types=1);

namespace App\Broadcasting;

use App\Models\Automation;
use App\Models\User;

class AutomationChannel
{
    public function join(User $user, Automation $automation): bool
    {
        return $automation->workspace->hasMember($user);
    }
}
