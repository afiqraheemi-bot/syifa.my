<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Domain\Aggregates\Tenant\Events;

use DateTimeImmutable;

final readonly class TenantDeletedOrAnonymized
{
    public function __construct(
        public string $tenantId,
        public string $outcome,
        public DateTimeImmutable $occurredAt,
    ) {}
}
