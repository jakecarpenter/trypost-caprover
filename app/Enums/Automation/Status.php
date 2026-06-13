<?php

declare(strict_types=1);

namespace App\Enums\Automation;

enum Status: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
}
