<?php

declare(strict_types=1);

namespace App\Enums\Automation;

/**
 * Schedule interval units for a Schedule trigger. Mirrors the frontend
 * ScheduleField const (resources/js/types/automation/schedule-field.ts).
 */
enum ScheduleField: string
{
    case Minutes = 'minutes';
    case Hours = 'hours';
    case Days = 'days';
    case Weeks = 'weeks';
    case Months = 'months';
}
