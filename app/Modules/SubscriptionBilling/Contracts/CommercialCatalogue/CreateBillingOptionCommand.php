<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue;

/**
 * Create-billing-option intent data.
 *
 * Calendar dates use the canonical `YYYY-MM-DD` format.
 * `occurredAt` uses RFC 3339 UTC in the canonical `YYYY-MM-DDTHH:MM:SSZ` format.
 */
final readonly class CreateBillingOptionCommand
{
    public function __construct(
        public string $code,
        public string $name,
        public string $recurrenceClassification,
        public ?string $intervalUnit,
        public ?int $intervalCount,
        public int $displayOrder,
        public string $effectiveStart,
        public ?string $effectiveEnd,
        public string $occurredAt,
        public string $actorPlatformIdentityId,
        public string $correlationId,
    ) {}
}
