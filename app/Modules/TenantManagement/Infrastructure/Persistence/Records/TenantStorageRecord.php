<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Infrastructure\Persistence\Records;

use DateTimeImmutable;

final readonly class TenantStorageRecord
{
    public function __construct(
        public string $id,
        public string $status,
        public int $version,
        public DateTimeImmutable $provisionedAt,
        public ?DateTimeImmutable $activatedAt = null,
        public ?DateTimeImmutable $suspendedAt = null,
        public ?DateTimeImmutable $reactivatedAt = null,
        public ?DateTimeImmutable $offboardingStartedAt = null,
        public ?DateTimeImmutable $deletedOrAnonymizedAt = null,
        public ?string $adminRoutingLabel = null,
    ) {}
}
