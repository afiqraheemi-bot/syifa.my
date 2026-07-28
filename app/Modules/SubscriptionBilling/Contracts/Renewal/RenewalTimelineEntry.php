<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Renewal;

use DateTimeImmutable;

final readonly class RenewalTimelineEntry
{
    public function __construct(
        public string $entryId,
        public string $subscriptionId,
        public string $renewalId,
        public string $paymentId,
        public string $eventType,
        public ?string $actorId,
        public string $correlationId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
