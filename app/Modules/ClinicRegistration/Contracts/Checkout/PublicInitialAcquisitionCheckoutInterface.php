<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Checkout;

interface PublicInitialAcquisitionCheckoutInterface
{
    public function execute(
        StartPublicInitialAcquisitionCheckoutCommand $command,
    ): PublicInitialAcquisitionCheckoutResult;
}
