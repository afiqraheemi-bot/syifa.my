<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue;

/**
 * Plan read-model data.
 *
 * `createdAt` and `lastChangedAt` use RFC 3339 UTC in the canonical `YYYY-MM-DDTHH:MM:SSZ` format.
 */
final readonly class PlanData
{
    public function __construct(
        public string $planId,
        public string $code,
        public string $name,
        public string $description,
        public string $status,
        public int $displayOrder,
        public string $createdAt,
        public string $lastChangedAt,
    ) {}
}
