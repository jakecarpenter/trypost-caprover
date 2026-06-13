<?php

declare(strict_types=1);

namespace App\Enums\Automation\Trigger;

enum Type: string
{
    case Schedule = 'schedule';
    case PostPublished = 'post_published';
    case PostScheduled = 'post_scheduled';
}
