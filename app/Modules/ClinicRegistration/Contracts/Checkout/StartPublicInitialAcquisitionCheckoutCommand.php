<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Checkout;

use DateTimeImmutable;

final readonly class StartPublicInitialAcquisitionCheckoutCommand
{
    public function __construct(
        public string $trackingCredential,
        public string $planOfferingId,
        public DateTimeImmutable $occurredAt,
        public string $correlationId,
    ) {}
}
