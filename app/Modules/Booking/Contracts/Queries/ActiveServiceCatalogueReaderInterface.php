<?php

declare(strict_types=1);

namespace App\Modules\Booking\Contracts\Queries;

interface ActiveServiceCatalogueReaderInterface
{
    /** @return list<PublicBookingFormServiceData> */
    public function forTenant(string $tenantId): array;
}
