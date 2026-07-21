<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\ValueObjects;

use App\Modules\Booking\Domain\Exceptions\InvalidBookingValueException;

final readonly class PatientPhone
{
    private const int MAX_LENGTH = 40;

    public function __construct(public string $value)
    {
        if (trim($value) === '') {
            throw new InvalidBookingValueException('Patient phone must not be blank.');
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw new InvalidBookingValueException('Patient phone is too long.');
        }
    }
}
