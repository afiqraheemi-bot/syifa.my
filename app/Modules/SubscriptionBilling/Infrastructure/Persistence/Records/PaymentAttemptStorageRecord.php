<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Persistence\Records;

use DateTimeImmutable;

final readonly class PaymentAttemptStorageRecord
{
    public function __construct(
        public string $paymentId,
        public string $attemptReference,
        public string $status,
        public string $providerKey,
        public ?string $providerPaymentReference,
        public ?string $failureReasonCode,
        public DateTimeImmutable $startedAt,
        public DateTimeImmutable $lastChangedAt,
        public int $position,
    ) {}
}
