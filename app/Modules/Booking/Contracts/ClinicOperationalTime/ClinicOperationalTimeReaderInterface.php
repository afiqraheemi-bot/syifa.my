<?php

declare(strict_types=1);

namespace App\Modules\Booking\Contracts\ClinicOperationalTime;

interface ClinicOperationalTimeReaderInterface
{
    /** @throws ClinicOperationalTimeNotFoundException */
    public function forTrustedTenant(string $tenantId): ClinicOperationalTimeData;
}
