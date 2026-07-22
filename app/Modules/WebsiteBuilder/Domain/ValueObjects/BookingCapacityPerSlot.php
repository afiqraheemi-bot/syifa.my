<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\ValueObjects;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidClinicOperationalTimeException;

final readonly class BookingCapacityPerSlot
{
    public function __construct(public int $value)
    {
        if ($value < 1 || $value > 10) {
            throw new InvalidClinicOperationalTimeException('Booking capacity per slot must be between 1 and 10.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
