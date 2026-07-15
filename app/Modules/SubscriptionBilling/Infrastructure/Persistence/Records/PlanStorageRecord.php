<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Persistence\Records;

use DateTimeImmutable;

final readonly class PlanStorageRecord
{
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
        public string $description,
        public string $status,
        public int $displayOrder,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $lastChangedAt,
        public int $version,
    ) {}
}
