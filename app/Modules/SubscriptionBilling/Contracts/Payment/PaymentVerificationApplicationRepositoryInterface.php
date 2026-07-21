<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

use DateTimeImmutable;

interface PaymentVerificationApplicationRepositoryInterface
{
    public function register(string $receiptId, DateTimeImmutable $now): PaymentVerificationApplication;

    public function claim(string $applicationId, DateTimeImmutable $now, int $leaseSeconds): ?PaymentVerificationApplication;

    public function find(string $applicationId): ?PaymentVerificationApplication;

    public function complete(string $applicationId, string $claimToken, PaymentVerificationApplicationStatus $status, PaymentVerificationApplicationResultCode $resultCode, DateTimeImmutable $now, ?DateTimeImmutable $nextAttemptAt = null): bool;
}
