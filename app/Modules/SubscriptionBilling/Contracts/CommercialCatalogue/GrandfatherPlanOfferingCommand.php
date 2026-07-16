<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue;

/**
 * RFC 3339 UTC instant in the canonical `YYYY-MM-DDTHH:MM:SSZ` format.
 */
final readonly class GrandfatherPlanOfferingCommand
{
    public function __construct(
        public string $planOfferingId,
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
