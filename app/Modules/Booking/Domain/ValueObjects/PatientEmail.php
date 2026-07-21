<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\ValueObjects;

use App\Modules\Booking\Domain\Exceptions\InvalidBookingValueException;

final readonly class PatientEmail
{
    private const int MAX_LENGTH = 254;

    public function __construct(public string $value)
    {
        if (trim($value) === '') {
            throw new InvalidBookingValueException('Patient email must not be blank.');
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw new InvalidBookingValueException('Patient email is too long.');
        }
    }
}
