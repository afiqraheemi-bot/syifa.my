<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue;

/**
 * Create-plan-offering intent data.
 *
 * Calendar dates use the canonical `YYYY-MM-DD` format.
 * `occurredAt` uses RFC 3339 UTC in the canonical `YYYY-MM-DDTHH:MM:SSZ` format.
 */
final readonly class CreatePlanOfferingCommand
{
    public function __construct(
        public string $planId,
        public string $billingOptionId,
        public int $amountMinor,
        public string $currencyCode,
        public string $effectiveStart,
        public ?string $effectiveEnd,
        public string $capabilityConfigurationReference,
        public int $displayOrder,
        public string $occurredAt,
        public string $actorPlatformIdentityId,
        public string $correlationId,
    ) {}
}
