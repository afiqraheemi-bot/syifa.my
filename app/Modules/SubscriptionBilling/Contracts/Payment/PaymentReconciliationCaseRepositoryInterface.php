<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

use DateTimeImmutable;

interface PaymentReconciliationCaseRepositoryInterface
{
    public function open(string $receiptId, string $paymentId, string $attemptReference, string $reasonCode, DateTimeImmutable $openedAt): string;
}
