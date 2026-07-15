<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects;

use App\Modules\PlatformAdministration\Domain\Authorization\Exceptions\InvalidPlatformAuthorizationValueException;

final readonly class PlatformPermissionKey
{
    public function __construct(public string $value)
    {
        if (preg_match('/^[a-z][a-z0-9._-]{0,99}$/', $value) !== 1) {
            throw new InvalidPlatformAuthorizationValueException('Platform Permission key has an invalid stable identifier.');
        }
    }
}
