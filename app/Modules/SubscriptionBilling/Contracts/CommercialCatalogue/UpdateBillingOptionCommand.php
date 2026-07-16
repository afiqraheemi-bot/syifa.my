<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue;

/**
 * RFC 3339 UTC instant in the canonical `YYYY-MM-DDTHH:MM:SSZ` format.
 *
 * `availability` is Billing Option's current governed field, not a named
 * lifecycle transition — Billing Option has no approved lifecycle endpoint.
 * HTTP delivery must treat it as an editable governed field only, until a
 * later Domain decision supersedes it.
 */
final readonly class UpdateBillingOptionCommand
{
    public function __construct(
        public string $billingOptionId,
        public string $name,
        public string $availability,
        public string $recurrenceClassification,
        public ?string $intervalUnit,
        public ?int $intervalCount,
        public string $effectiveStart,
        public ?string $effectiveEnd,
        public int $displayOrder,
        public int $expectedVersion,
        public string $occurredAt,
        public string $actorPlatformIdentityId,
        public string $correlationId,
    ) {
        if ($this->expectedVersion < 1) {
            throw new \InvalidArgumentException('Expected version must be at least 1.');
        }
    }
}
