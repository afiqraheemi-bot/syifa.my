<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\Administration;

use DateTimeImmutable;

final readonly class EstablishClinicOwnerCommand
{
    public function __construct(
        public string $tenantId,
        public string $name,
        public string $email,
        public string $actorPlatformIdentityId,
        public string $correlationId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
