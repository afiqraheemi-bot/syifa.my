<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Application\Authentication;

use App\Modules\TenantManagement\Application\Authentication\Exceptions\InvalidClinicOwnerPasswordException;
use SensitiveParameter;

final class ClinicOwnerPasswordPolicy
{
    private const int MINIMUM_CODE_POINTS = 15;

    private const int MAXIMUM_CODE_POINTS = 1024;

    public function validate(#[SensitiveParameter] string $password): void
    {
        if (! mb_check_encoding($password, 'UTF-8')) {
            throw new InvalidClinicOwnerPasswordException('A Clinic Owner password must be valid Unicode.');
        }

        $length = mb_strlen($password, 'UTF-8');

        if ($length < self::MINIMUM_CODE_POINTS || $length > self::MAXIMUM_CODE_POINTS) {
            throw new InvalidClinicOwnerPasswordException(
                'A Clinic Owner password must contain between 15 and 1024 Unicode code points.',
            );
        }
    }
}
