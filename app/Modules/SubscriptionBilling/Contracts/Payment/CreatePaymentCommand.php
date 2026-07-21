<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

use DateTimeImmutable;

final readonly class CreatePaymentCommand
{
    public function __construct(
        public string $commercialOfferId,
        public string $idempotencyKey,
        public DateTimeImmutable $occurredAt,
        public string $correlationId,
    ) {}
}
