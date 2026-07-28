<?php

declare(strict_types=1);

namespace App\Modules\Booking\Contracts\Queries;

interface ActiveServiceReferenceReaderInterface
{
    /** @return list<string> */
    public function forTenant(string $tenantId): array;
}
