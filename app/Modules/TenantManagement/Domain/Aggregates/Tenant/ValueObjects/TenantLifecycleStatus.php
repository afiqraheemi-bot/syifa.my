<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects;

enum TenantLifecycleStatus: string
{
    case Provisioning = 'provisioning';
    case Active = 'active';
    case Suspended = 'suspended';
    case Reactivated = 'reactivated';
    case Offboarding = 'offboarding';
    case Deleted = 'deleted';
    case Anonymized = 'anonymized';
}
