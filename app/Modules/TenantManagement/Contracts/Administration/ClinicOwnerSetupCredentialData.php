<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\Administration;

final readonly class ClinicOwnerSetupCredentialData
{
    public function __construct(
        public string $tenantId,
        public string $authorityId,
        public string $email,
    ) {}
}
