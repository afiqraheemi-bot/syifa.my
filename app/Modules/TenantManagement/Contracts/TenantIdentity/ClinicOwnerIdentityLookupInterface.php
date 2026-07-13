<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\TenantIdentity;

interface ClinicOwnerIdentityLookupInterface
{
    public function findActiveForTenant(
        string $tenantId,
        string $clinicOwnerIdentityId,
    ): ?ClinicOwnerIdentityData;
}
