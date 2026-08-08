<?php

declare(strict_types=1);

namespace App\Modules\AcquisitionOffer\Contracts\Commands;

use DateTimeImmutable;

final readonly class PrepareInitialCommercialOfferCommand
{
    public function __construct(
        public string $registrationTrackingCredential,
        public string $planOfferingId,
        public DateTimeImmutable $occurredAt,
        public string $correlationId,
    ) {}
}
