<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\Provisioning;

use DateTimeImmutable;

final readonly class ProvisionTenantCommand
{
    public function __construct(
        public string $tenantId,
        public string $sourceEventId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
