<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\Session;

use DateTimeImmutable;

final readonly class AuthenticatedClinicOwnerSessionData
{
    public function __construct(
        public string $tenantId,
        public string $role,
        public DateTimeImmutable $idleExpiresAt,
        public DateTimeImmutable $absoluteExpiresAt,
    ) {}
}
