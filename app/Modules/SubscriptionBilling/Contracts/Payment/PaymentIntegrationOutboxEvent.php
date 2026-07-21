<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

use DateTimeImmutable;

final readonly class PaymentIntegrationOutboxEvent
{
    /** @param array<string, scalar|null> $payload */
    public function __construct(public string $id, public string $type, public int $eventVersion, public string $paymentId, public array $payload, public DateTimeImmutable $occurredAt) {}
}
