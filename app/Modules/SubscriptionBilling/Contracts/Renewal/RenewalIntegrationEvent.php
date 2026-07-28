<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Renewal;

use DateTimeImmutable;

final readonly class RenewalIntegrationEvent
{
    /**
     * @param  array<string, string>  $payload
     */
    public function __construct(
        public string $eventId,
        public string $eventType,
        public int $eventVersion,
        public string $subscriptionId,
        public string $renewalId,
        public string $paymentId,
        public array $payload,
        public DateTimeImmutable $occurredAt,
    ) {}
}
