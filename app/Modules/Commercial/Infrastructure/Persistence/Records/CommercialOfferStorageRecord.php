<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Infrastructure\Persistence\Records;

use DateTimeImmutable;

final readonly class CommercialOfferStorageRecord
{
    public function __construct(
        public string $id,
        public ?string $platformIdentityId,
        public string $clinicRegistrationId,
        public ?string $tenantId,
        public string $status,
        public string $planOfferingId,
        public string $planId,
        public string $billingCycleId,
        public string $billingPeriodStart,
        public string $billingPeriodEnd,
        public string $offeringConfigurationVersion,
        public string $capabilityConfigurationReference,
        public int $subtotalAmountMinor,
        public int $totalAmountMinor,
        public string $currency,
        public DateTimeImmutable $expiresAt,
        public ?string $claimedPaymentId,
        public ?DateTimeImmutable $claimedAt,
        public ?DateTimeImmutable $cancelledAt,
        public ?DateTimeImmutable $expiredAt,
        public string $correlationId,
        public int $version,
    ) {}
}
