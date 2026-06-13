<?php

declare(strict_types=1);

namespace App\Enums\Automation\Condition;

/**
 * Output handles of a Condition node. The matched branch determines which
 * handle the run continues down — these values must mirror the `id`s of the
 * handles rendered in the frontend ConditionNode (resources/js/types/automation
 * /condition-handle.ts) and the `source_handle` stored on edges.
 */
enum Handle: string
{
    case Yes = 'yes';
    case No = 'no';
}
