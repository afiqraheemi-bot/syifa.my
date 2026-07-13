<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Domain\Aggregates\Tenant\Events;

use DateTimeImmutable;

final readonly class TenantOffboardingStarted
{
    public function __construct(
        public string $tenantId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
