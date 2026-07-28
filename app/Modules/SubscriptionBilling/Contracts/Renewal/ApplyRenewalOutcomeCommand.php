<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Renewal;

use DateTimeImmutable;

final readonly class ApplyRenewalOutcomeCommand
{
    public function __construct(
        public string $sourceEventId,
        public string $paymentId,
        public string $outcome,
        public string $correlationId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
