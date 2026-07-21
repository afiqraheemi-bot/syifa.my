<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

use DateTimeImmutable;

final readonly class PaymentVerificationApplication
{
    public function __construct(
        public string $id,
        public string $receiptId,
        public PaymentVerificationApplicationStatus $status,
        public int $attemptCount,
        public ?string $claimToken = null,
        public ?DateTimeImmutable $leaseExpiresAt = null,
        public ?DateTimeImmutable $nextAttemptAt = null,
        public ?PaymentVerificationApplicationResultCode $resultCode = null,
    ) {}
}
