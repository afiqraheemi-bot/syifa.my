<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Contracts\Commands;

use DateTimeImmutable;

final readonly class PrepareCommercialOfferCommand
{
    public function __construct(
        public string $platformIdentityId,
        public string $clinicRegistrationId,
        public string $planOfferingId,
        public DateTimeImmutable $occurredAt,
        public string $correlationId,
    ) {}
}
