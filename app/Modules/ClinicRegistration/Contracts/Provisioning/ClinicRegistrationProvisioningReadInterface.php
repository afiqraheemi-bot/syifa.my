<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Provisioning;

use App\Modules\ClinicRegistration\Contracts\Data\ClinicRegistrationData;

interface ClinicRegistrationProvisioningReadInterface
{
    public function submitted(string $registrationId): ?ClinicRegistrationData;
}
