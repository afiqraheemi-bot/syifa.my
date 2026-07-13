<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\Authentication;

final readonly class ClinicOwnerAuthenticatedPrincipal
{
    public function __construct(
        public string $tenantId,
        public string $authorityId,
        public string $clinicOwnerIdentityId,
    ) {}
}
