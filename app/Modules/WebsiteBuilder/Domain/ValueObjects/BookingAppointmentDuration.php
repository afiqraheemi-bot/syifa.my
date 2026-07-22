<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\ValueObjects;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidClinicOperationalTimeException;

final readonly class BookingAppointmentDuration
{
    private const array ALLOWED = [15, 20, 30, 45, 60];

    public function __construct(public int $minutes)
    {
        if (! in_array($minutes, self::ALLOWED, true)) {
            throw new InvalidClinicOperationalTimeException('Appointment duration must be 15, 20, 30, 45, or 60 minutes.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->minutes === $other->minutes;
    }
}
