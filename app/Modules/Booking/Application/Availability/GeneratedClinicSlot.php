<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Availability;

use DateTimeImmutable;

final readonly class GeneratedClinicSlot
{
    public function __construct(
        public string $localDate,
        public string $localStart,
        public string $localEnd,
        public string $timezone,
        public DateTimeImmutable $startsAtUtc,
        public DateTimeImmutable $endsAtUtc,
        public int $durationMinutes,
    ) {}
}
