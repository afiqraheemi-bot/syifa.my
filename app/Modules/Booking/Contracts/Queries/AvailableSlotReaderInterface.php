<?php

declare(strict_types=1);

namespace App\Modules\Booking\Contracts\Queries;

interface AvailableSlotReaderInterface
{
    /** @return list<AvailableSlotData> */
    public function forDate(string $trustedTenantId, string $localDate): array;
}
