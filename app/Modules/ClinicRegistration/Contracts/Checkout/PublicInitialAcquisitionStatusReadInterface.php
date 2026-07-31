<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Checkout;

interface PublicInitialAcquisitionStatusReadInterface
{
    public function forRegistration(string $clinicRegistrationReference): ?PublicInitialAcquisitionStatusData;
}
