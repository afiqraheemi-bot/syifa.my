<?php

declare(strict_types=1);

namespace App\Modules\AcquisitionOffer\Contracts\Commands;

use DateTimeImmutable;

final readonly class ClaimCommercialOfferCommand
{
    public function __construct(
        public string $commercialOfferId,
        public string $paymentId,
        public string $trustedConsumer,
        public int $expectedVersion,
        public DateTimeImmutable $occurredAt,
        public string $correlationId,
    ) {}
}
