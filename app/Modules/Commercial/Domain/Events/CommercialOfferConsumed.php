<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Domain\Events;

use DateTimeImmutable;

final readonly class CommercialOfferConsumed
{
    public function __construct(
        public string $commercialOfferId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
