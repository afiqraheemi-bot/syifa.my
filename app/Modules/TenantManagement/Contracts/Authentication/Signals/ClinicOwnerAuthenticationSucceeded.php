<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\Authentication\Signals;

use DateTimeImmutable;

final readonly class ClinicOwnerAuthenticationSucceeded
{
    public function __construct(
        public string $tenantId,
        public string $authorityId,
        public string $clinicOwnerIdentityId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
