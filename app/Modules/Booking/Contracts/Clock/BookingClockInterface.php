<?php

declare(strict_types=1);

namespace App\Modules\Booking\Contracts\Clock;

use DateTimeImmutable;

interface BookingClockInterface
{
    public function now(): DateTimeImmutable;
}
