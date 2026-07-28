<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

final readonly class PaymentData
{
    public function __construct(
        public string $paymentId,
        public string $commercialOfferId,
        public string $clinicRegistrationId,
        public ?string $platformIdentityId,
        public ?string $tenantId,
        public int $amountMinor,
        public string $currency,
        public string $idempotencyKey,
        public string $status,
        public ?string $providerKey,
        public ?string $providerPaymentReference,
        public ?string $failureReasonCode,
        public string $createdAt,
        public string $lastChangedAt,
        public int $version,
    ) {}
}
