<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Queries;

use App\Modules\ClinicRegistration\Contracts\Data\ClinicRegistrationData;

interface ClinicRegistrationQueryInterface
{
    public function currentForPlatformIdentity(string $platformIdentityId): ?ClinicRegistrationData;

    public function currentForTrackingCredential(string $trackingCredential): ?ClinicRegistrationData;
}
