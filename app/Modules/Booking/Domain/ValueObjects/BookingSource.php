<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\ValueObjects;

use App\Modules\Booking\Domain\Exceptions\InvalidBookingValueException;

enum BookingSource: string
{
    case Website = 'WEBSITE';
    case WhatsApp = 'WHATSAPP';
    case Phone = 'PHONE';
    case WalkIn = 'WALK_IN';
    case Staff = 'STAFF';

    public static function fromStored(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new InvalidBookingValueException('Stored Booking source is invalid.');
    }

    public function isManual(): bool
    {
        return $this !== self::Website;
    }
}
