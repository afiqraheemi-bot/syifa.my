<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Contracts\Renewal;

use DateTimeImmutable;

final readonly class PrepareRenewalOfferInput
{
    public function __construct(
        public string $subscriptionId,
        public string $renewalId,
        public string $idempotencyKey,
        public string $initiatingActorId,
        public DateTimeImmutable $occurredAt,
        public string $correlationId,
    ) {}
}
