<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\TenantIdentity;

use DateTimeImmutable;

final readonly class ClinicOwnerIdentityData
{
    public function __construct(
        public string $tenantId,
        public string $clinicOwnerAuthorityId,
        public string $clinicOwnerIdentityId,
        public string $email,
        public string $name,
        public string $authorityStatus,
        public DateTimeImmutable $establishedAt,
        public ?DateTimeImmutable $revokedAt,
    ) {}
}
