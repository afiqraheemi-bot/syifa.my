<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\ValueObjects;

use App\Modules\Booking\Domain\Exceptions\InvalidBookingValueException;

final readonly class BookingReference
{
    private const int MAX_LENGTH = 120;

    public function __construct(public string $value)
    {
        if (trim($value) === '') {
            throw new InvalidBookingValueException('Booking reference must not be blank.');
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw new InvalidBookingValueException('Booking reference is too long.');
        }
    }
}
