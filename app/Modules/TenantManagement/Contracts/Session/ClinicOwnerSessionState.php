<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\Session;

use DateTimeImmutable;

final readonly class ClinicOwnerSessionState
{
    public function __construct(
        public string $tenantId,
        public string $authorityId,
        public string $clinicOwnerIdentityId,
        public string $role,
        public DateTimeImmutable $authenticatedAt,
        public DateTimeImmutable $lastActivityAt,
        public DateTimeImmutable $absoluteExpiresAt,
    ) {}
}
