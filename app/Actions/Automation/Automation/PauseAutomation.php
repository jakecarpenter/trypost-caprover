<?php

declare(strict_types=1);

namespace App\Actions\Automation\Automation;

use App\Enums\Automation\Status;
use App\Models\Automation;

class PauseAutomation
{
    public function __invoke(Automation $automation): Automation
    {
        $automation->update([
            'status' => Status::Paused,
            'paused_at' => now(),
        ]);

        return $automation;
    }
}
