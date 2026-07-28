<?php

declare(strict_types=1);

namespace App\Modules\Booking\Contracts\Provisioning;

interface ProvisionBookingFoundationInterface
{
    public function execute(string $tenantId): int;
}
