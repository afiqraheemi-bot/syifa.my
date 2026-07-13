<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Domain\TenantContext\ValueObjects;

use App\Modules\TenantManagement\Domain\TenantContext\Exceptions\InvalidTenantContextValueException;

final readonly class PlatformIdentityId
{
    public function __construct(public string $value)
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) !== 1) {
            throw new InvalidTenantContextValueException('A Platform Identity identifier must be a UUID.');
        }
    }
}
