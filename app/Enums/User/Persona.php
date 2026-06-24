<?php

declare(strict_types=1);

namespace App\Enums\User;

enum Persona: string
{
    case Creator = 'creator';
    case Freelancer = 'freelancer';
    case Developer = 'developer';
    case Startup = 'startup';
    case Agency = 'agency';
    case SmallBusiness = 'small_business';
    case Marketer = 'marketer';
    case OnlineStore = 'online_store';
    case Other = 'other';
}
