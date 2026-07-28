<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Renewal;

use DateTimeImmutable;

final readonly class CreatePaymentSessionInput
{
    public function __construct(
        public string $renewalId,
        public string $paymentId,
        public DateTimeImmutable $commercialOfferValidUntil,
        public string $idempotencyKey,
        public DateTimeImmutable $requestedAt,
    ) {}
}
