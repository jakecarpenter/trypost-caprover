<?php

declare(strict_types=1);

namespace App\Enums\Automation;

/**
 * Authentication strategies for the HTTP Request node. Mirrors the frontend
 * AuthType const (resources/js/types/automation/auth-type.ts).
 */
enum AuthType: string
{
    case None = 'none';
    case Bearer = 'bearer';
    case Basic = 'basic';
    case ApiKey = 'api_key';
}
