<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue;

/**
 * Billing option read-model data.
 *
 * `effectiveStart` and `effectiveEnd` use the canonical `YYYY-MM-DD` format.
 */
final readonly class BillingOptionData
{
    public function __construct(
        public string $billingOptionId,
        public string $code,
        public string $name,
        public string $availability,
        public string $recurrenceClassification,
        public ?string $intervalUnit,
        public ?int $intervalCount,
        public string $effectiveStart,
        public ?string $effectiveEnd,
        public int $displayOrder,
        public int $version = 1,
    ) {}
}
