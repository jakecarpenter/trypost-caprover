<?php

declare(strict_types=1);

namespace App\Enums\Automation\Run;

enum Status: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Waiting = 'waiting';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
