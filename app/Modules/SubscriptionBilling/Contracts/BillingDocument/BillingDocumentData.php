<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\BillingDocument;

final readonly class BillingDocumentData
{
    public function __construct(
        public string $paymentId,
        public string $subscriptionId,
        public string $tenantId,
        public string $clinicName,
        public string $invoiceNumber,
        public ?string $receiptNumber,
        public string $purpose,
        public string $planName,
        public string $billingCycleName,
        public string $periodStartsOn,
        public string $periodEndsOn,
        public int $amountMinor,
        public string $currency,
        public string $paymentStatus,
        public string $issuedAt,
        public ?string $paidAt,
        public ?string $providerKey,
        public ?string $providerReference,
    ) {}
}
