<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Persistence\Records;

use DateTimeImmutable;

final readonly class SubscriptionStorageRecord
{
    /** @param list<string> $entitlementCapabilities */
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $clinicRegistrationId,
        public string $paymentId,
        public string $commercialOfferId,
        public string $planId,
        public string $billingCycleId,
        public int $amountMinor,
        public string $currency,
        public string $startsOn,
        public string $endsOn,
        public string $status,
        public string $entitlementConfigurationVersion,
        public string $entitlementStatus,
        public array $entitlementCapabilities,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $lastChangedAt,
        public int $version,
    ) {}
}
