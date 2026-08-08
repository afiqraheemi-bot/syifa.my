<?php

declare(strict_types=1);

namespace App\Modules\AcquisitionOffer\Contracts\Commands;

use DateTimeImmutable;

final readonly class ExpireCommercialOfferCommand
{
    public function __construct(
        public string $commercialOfferId,
        public int $expectedVersion,
        public DateTimeImmutable $occurredAt,
        public string $correlationId,
    ) {}
}
