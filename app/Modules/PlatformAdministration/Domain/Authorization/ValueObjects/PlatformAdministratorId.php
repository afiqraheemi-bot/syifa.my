<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects;

use App\Modules\PlatformAdministration\Domain\Authorization\Exceptions\InvalidPlatformAuthorizationValueException;

final readonly class PlatformAdministratorId
{
    public function __construct(public string $value)
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) !== 1) {
            throw new InvalidPlatformAuthorizationValueException('Platform Administrator ID must be a UUID.');
        }
    }
}
