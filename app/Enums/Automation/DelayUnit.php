<?php

declare(strict_types=1);

namespace App\Enums\Automation;

/**
 * Time units for the Delay node. Mirrors the frontend DelayUnit const
 * (resources/js/types/automation/delay-unit.ts).
 */
enum DelayUnit: string
{
    case Minutes = 'minutes';
    case Hours = 'hours';
    case Days = 'days';
}
