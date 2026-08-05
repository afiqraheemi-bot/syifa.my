<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Checkout;

interface CompleteLocalDemoAcquisitionInterface
{
    public function execute(string $trackingCredential, string $correlationId): void;
}
