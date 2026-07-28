<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

use DateTimeImmutable;

final readonly class CreateInitialAcquisitionPaymentCommand
{
    public function __construct(
        public string $clinicRegistrationReference,
        public string $commercialOfferReference,
        public string $futureTenantId,
        public DateTimeImmutable $occurredAt,
        public string $correlationId,
    ) {}
}
