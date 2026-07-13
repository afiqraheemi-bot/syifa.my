<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\Authentication;

interface ClinicOwnerAuthenticationInterface
{
    public function authenticate(ClinicOwnerAuthenticationCommand $command): ClinicOwnerAuthenticationResult;
}
