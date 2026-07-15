<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Persistence\Records;

use DateTimeImmutable;

final readonly class BillingOptionStorageRecord
{
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
        public string $availability,
        public string $recurrenceClassification,
        public ?string $intervalUnit,
        public ?int $intervalCount,
        public DateTimeImmutable $effectiveStart,
        public ?DateTimeImmutable $effectiveEnd,
        public int $displayOrder,
        public int $version,
    ) {}
}
