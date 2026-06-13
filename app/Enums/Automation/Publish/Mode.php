<?php

declare(strict_types=1);

namespace App\Enums\Automation\Publish;

enum Mode: string
{
    case Now = 'now';
    case Scheduled = 'scheduled';
    case Draft = 'draft';
}
