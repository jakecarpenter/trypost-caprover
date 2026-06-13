<?php

declare(strict_types=1);

namespace App\Enums\Automation\Condition;

enum Operator: string
{
    case Contains = 'contains';
    case NotContains = 'not_contains';
    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case Matches = 'matches';
    case GreaterThan = 'greater_than';
    case LessThan = 'less_than';
}
