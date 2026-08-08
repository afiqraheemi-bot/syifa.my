<?php

declare(strict_types=1);

namespace App\Modules\AcquisitionOffer\Domain\Events;

use DateTimeImmutable;

final readonly class CommercialOfferPrepared
{
    public function __construct(
        public string $commercialOfferId,
        public ?string $platformIdentityId,
        public string $clinicRegistrationId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
