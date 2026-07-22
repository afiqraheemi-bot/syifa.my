<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure;

use App\Modules\Booking\Contracts\Clock\BookingClockInterface;
use DateTimeImmutable;

final readonly class SystemBookingClock implements BookingClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable;
    }
}
