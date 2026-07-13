<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\Authentication;

enum ClinicOwnerAuthenticationOutcome: string
{
    case Authenticated = 'authenticated';
    case Rejected = 'rejected';
}
