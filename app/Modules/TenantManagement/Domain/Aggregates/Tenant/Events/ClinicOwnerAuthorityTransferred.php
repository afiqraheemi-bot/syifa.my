<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Domain\Aggregates\Tenant\Events;

use DateTimeImmutable;

final readonly class ClinicOwnerAuthorityTransferred
{
    public function __construct(
        public string $tenantId,
        public string $previousClinicOwnerAuthorityId,
        public string $currentClinicOwnerAuthorityId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
