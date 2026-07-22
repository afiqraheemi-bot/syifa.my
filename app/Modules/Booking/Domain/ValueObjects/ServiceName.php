<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\ValueObjects;

use App\Modules\Booking\Domain\Exceptions\InvalidServiceValueException;

final readonly class ServiceName
{
    private const int MAX_LENGTH = 200;

    public function __construct(public string $value)
    {
        if (trim($value) === '') {
            throw new InvalidServiceValueException('Service name must not be blank.');
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw new InvalidServiceValueException('Service name is too long.');
        }
    }
}
