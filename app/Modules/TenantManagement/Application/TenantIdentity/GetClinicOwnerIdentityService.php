<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Application\TenantIdentity;

use App\Modules\TenantManagement\Contracts\TenantIdentity\ClinicOwnerIdentityData;
use App\Modules\TenantManagement\Contracts\TenantIdentity\ClinicOwnerIdentityLookupInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;

final readonly class GetClinicOwnerIdentityService
{
    public function __construct(private ClinicOwnerIdentityLookupInterface $clinicOwnerIdentities) {}

    public function execute(
        TenantId $tenantId,
        ClinicOwnerIdentityId $clinicOwnerIdentityId,
    ): ?ClinicOwnerIdentityData {
        return $this->clinicOwnerIdentities->findActiveForTenant(
            $tenantId->value,
            $clinicOwnerIdentityId->value,
        );
    }
}
