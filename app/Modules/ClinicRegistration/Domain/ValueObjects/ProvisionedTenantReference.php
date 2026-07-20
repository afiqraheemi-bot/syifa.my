<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Domain\ValueObjects;

use App\Modules\ClinicRegistration\Domain\Exceptions\InvalidClinicRegistrationValueException;

final readonly class ProvisionedTenantReference
{
    public function __construct(public string $value)
    {
        if (trim($value) === '' || mb_strlen($value) > 120) {
            throw new InvalidClinicRegistrationValueException('Provisioned tenant reference is invalid.');
        }
    }
}
