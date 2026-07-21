<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

use DateTimeImmutable;

final readonly class ProviderPaymentVerification
{
    public function __construct(
        public string $providerKey,
        public string $providerPaymentReference,
        public ProviderPaymentVerificationOutcome $outcome,
        public int $verifiedAmountMinor,
        public string $verifiedCurrency,
        public DateTimeImmutable $verifiedAt,
        public bool $providerObjectCorrelationPassed,
        public bool $environmentCorrelationSupported,
        public bool $environmentCorrelationPassed,
    ) {}
}
