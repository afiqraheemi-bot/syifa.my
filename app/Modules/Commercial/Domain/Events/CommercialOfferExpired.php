<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Domain\Events;

use DateTimeImmutable;

final readonly class CommercialOfferExpired
{
    public function __construct(
        public string $commercialOfferId,
        public string $platformIdentityId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
