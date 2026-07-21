<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

use DateTimeImmutable;

final readonly class NewProviderWebhookReceiptData
{
    public function __construct(
        public string $providerKey,
        public string $providerEventId,
        public string $eventType,
        public DateTimeImmutable $receivedAt,
        public ?string $providerPaymentReference = null,
        public ?string $paymentAttemptReference = null,
        public ?string $paymentId = null,
        public ?bool $signatureVerified = null,
        public ?string $payloadHash = null,
    ) {}
}
