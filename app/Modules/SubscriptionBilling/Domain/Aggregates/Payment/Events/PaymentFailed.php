<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Events;

use DateTimeImmutable;

final readonly class PaymentFailed
{
    public function __construct(
        public string $paymentId,
        public string $reasonCode,
        public DateTimeImmutable $occurredAt,
    ) {}
}
