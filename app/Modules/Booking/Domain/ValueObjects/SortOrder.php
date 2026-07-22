<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\ValueObjects;

use App\Modules\Booking\Domain\Exceptions\InvalidServiceValueException;

final readonly class SortOrder
{
    public function __construct(public int $value)
    {
        if ($this->value < 0) {
            throw new InvalidServiceValueException('Sort order must not be negative.');
        }
    }
}
