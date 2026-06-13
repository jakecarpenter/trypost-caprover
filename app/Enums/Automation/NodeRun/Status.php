<?php

declare(strict_types=1);

namespace App\Enums\Automation\NodeRun;

enum Status: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
