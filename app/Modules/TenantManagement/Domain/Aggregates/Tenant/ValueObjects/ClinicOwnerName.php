<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects;

use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Exceptions\InvalidTenantIdentityValueException;

final readonly class ClinicOwnerName
{
    public string $value;

    public function __construct(string $value)
    {
        $normalized = trim($value);

        if ($normalized === '' || mb_strlen($normalized) > 120) {
            throw new InvalidTenantIdentityValueException(
                'A Clinic Owner name must contain between 1 and 120 characters.',
            );
        }

        $this->value = $normalized;
    }
}
