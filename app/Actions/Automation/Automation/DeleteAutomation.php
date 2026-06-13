<?php

declare(strict_types=1);

namespace App\Actions\Automation\Automation;

use App\Models\Automation;

class DeleteAutomation
{
    public function __invoke(Automation $automation): void
    {
        $automation->delete();
    }
}
