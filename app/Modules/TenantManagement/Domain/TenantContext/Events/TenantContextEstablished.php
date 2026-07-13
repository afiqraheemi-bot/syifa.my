<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Domain\TenantContext\Events;

use DateTimeImmutable;

final readonly class TenantContextEstablished
{
    public function __construct(
        public string $tenantId,
        public string $role,
        public ?string $platformIdentityId,
        public ?string $assignmentId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
