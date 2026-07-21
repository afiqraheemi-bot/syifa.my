<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

use DateTimeImmutable;

final readonly class ProviderWebhookReceiptCompletion
{
    public function __construct(
        public ProviderWebhookReceiptStatus $status,
        public DateTimeImmutable $occurredAt,
        public ?ResolvedPaymentAttempt $attempt = null,
        public ?ProviderPaymentVerification $verification = null,
        public ?string $safeFailureLabel = null,
        public ?DateTimeImmutable $nextVerificationAttemptAt = null,
    ) {}
}
