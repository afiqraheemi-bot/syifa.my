<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

use DateTimeImmutable;

interface PaymentOutboxRepositoryInterface
{
    /** @param array<string, scalar|null> $payload */
    public function add(string $eventId, string $eventType, string $paymentId, array $payload, DateTimeImmutable $occurredAt): void;
}
