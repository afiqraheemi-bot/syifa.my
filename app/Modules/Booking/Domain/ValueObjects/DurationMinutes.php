<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\ValueObjects;

use App\Modules\Booking\Domain\Exceptions\InvalidServiceValueException;

final readonly class DurationMinutes
{
    private const int MAX_MINUTES = 1440;

    public function __construct(public int $value)
    {
        if ($this->value <= 0) {
            throw new InvalidServiceValueException('Duration minutes must be a positive integer.');
        }

        if ($this->value > self::MAX_MINUTES) {
            throw new InvalidServiceValueException('Duration minutes must not exceed one calendar day.');
        }
    }
}
