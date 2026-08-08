<?php

declare(strict_types=1);

namespace App\Modules\AcquisitionOffer\Domain\Events;

use DateTimeImmutable;

final readonly class CommercialOfferClaimed
{
    public function __construct(
        public string $commercialOfferId,
        public string $paymentId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
