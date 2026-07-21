<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\ValueObjects;

use App\Modules\Booking\Domain\Exceptions\InvalidBookingValueException;
use DateTimeImmutable;

final readonly class AppointmentTime
{
    public function __construct(public string $value)
    {
        $parsed = DateTimeImmutable::createFromFormat('!H:i', $value);

        if ($parsed === false || $parsed->format('H:i') !== $value) {
            throw new InvalidBookingValueException('Appointment time must be a valid 24-hour time in H:i format.');
        }
    }
}
