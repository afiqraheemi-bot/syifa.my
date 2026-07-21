<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Persistence\Records;

use DateTimeImmutable;

final readonly class PaymentStorageRecord
{
    public function __construct(
        public string $id,
        public string $commercialOfferId,
        public string $clinicRegistrationId,
        public string $platformIdentityId,
        public int $amountMinor,
        public string $currency,
        public string $idempotencyKey,
        public string $status,
        public ?string $providerKey,
        public ?string $providerPaymentReference,
        public ?string $failureReasonCode,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $lastChangedAt,
        public int $version,
    ) {}
}
