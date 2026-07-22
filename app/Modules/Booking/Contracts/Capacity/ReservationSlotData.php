<?php

declare(strict_types=1);

namespace App\Modules\Booking\Contracts\Capacity;

use DateTimeImmutable;

final readonly class ReservationSlotData
{
    public function __construct(
        public DateTimeImmutable $startsAtUtc,
        public DateTimeImmutable $endsAtUtc,
    ) {}
}
