<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects;

enum ClinicOwnerAuthorityStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';
}
