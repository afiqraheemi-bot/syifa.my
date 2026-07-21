<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Payment;
use DateTimeImmutable;

interface PaymentAuditInterface
{
    public function record(string $action, Payment $payment, DateTimeImmutable $occurredAt, string $correlationId): void;
}
