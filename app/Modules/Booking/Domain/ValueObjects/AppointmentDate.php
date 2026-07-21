<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\ValueObjects;

use App\Modules\Booking\Domain\Exceptions\InvalidBookingValueException;
use DateTimeImmutable;

final readonly class AppointmentDate
{
    public function __construct(public string $value)
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
            throw new InvalidBookingValueException('Appointment date must be a valid calendar date in Y-m-d format.');
        }
    }
}
