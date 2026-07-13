<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Domain\PlatformIdentity\ValueObjects;

use App\Modules\PlatformAdministration\Domain\PlatformIdentity\Exceptions\InvalidPlatformIdentityEmailException;

final readonly class PlatformIdentityEmail
{
    public string $value;

    public function __construct(string $value)
    {
        $normalized = mb_strtolower(trim($value));

        if (filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidPlatformIdentityEmailException('A platform identity email must be valid.');
        }

        $this->value = $normalized;
    }
}
