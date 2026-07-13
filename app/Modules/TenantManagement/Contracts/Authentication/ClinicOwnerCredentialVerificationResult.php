<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\Authentication;

final readonly class ClinicOwnerCredentialVerificationResult
{
    public function __construct(
        public bool $verified,
        public ?string $tenantId = null,
        public ?string $authorityId = null,
        public ?string $clinicOwnerIdentityId = null,
        public bool $emailVerified = false,
    ) {}
}
