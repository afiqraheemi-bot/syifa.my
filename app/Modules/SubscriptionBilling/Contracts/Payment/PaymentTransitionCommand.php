<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

use DateTimeImmutable;

final readonly class PaymentTransitionCommand
{
    public function __construct(
        public string $paymentId,
        public int $expectedVersion,
        public DateTimeImmutable $occurredAt,
        public string $correlationId,
        public ?string $reasonCode = null,
        public ?string $providerKey = null,
        public ?string $providerPaymentReference = null,
    ) {}
}
