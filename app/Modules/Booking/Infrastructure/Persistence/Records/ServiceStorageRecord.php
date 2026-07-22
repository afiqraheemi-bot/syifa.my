<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Persistence\Records;

use DateTimeImmutable;

final readonly class ServiceStorageRecord
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $name,
        public ?string $description,
        public ?int $durationMinutes,
        public int $sortOrder,
        public string $status,
        public DateTimeImmutable $domainCreatedAt,
        public DateTimeImmutable $domainUpdatedAt,
        public int $version,
    ) {}
}
