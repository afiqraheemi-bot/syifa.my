<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\ValueObjects;

use App\Modules\Booking\Domain\Exceptions\InvalidServiceValueException;

final readonly class ServiceDescription
{
    private const int MAX_LENGTH = 2000;

    public function __construct(public string $value)
    {
        if (trim($value) === '') {
            throw new InvalidServiceValueException('Service description must not be blank.');
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw new InvalidServiceValueException('Service description is too long.');
        }
    }
}
