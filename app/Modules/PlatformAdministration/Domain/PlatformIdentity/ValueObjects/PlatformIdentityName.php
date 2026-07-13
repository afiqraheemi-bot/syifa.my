<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Domain\PlatformIdentity\ValueObjects;

use App\Modules\PlatformAdministration\Domain\PlatformIdentity\Exceptions\InvalidPlatformIdentityNameException;

final readonly class PlatformIdentityName
{
    public string $value;

    public function __construct(string $value)
    {
        $normalized = trim($value);

        if ($normalized === '' || mb_strlen($normalized) > 120) {
            throw new InvalidPlatformIdentityNameException(
                'A platform identity name must contain between 1 and 120 characters.',
            );
        }

        $this->value = $normalized;
    }
}
