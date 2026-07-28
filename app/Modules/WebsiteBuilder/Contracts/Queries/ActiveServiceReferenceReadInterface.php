<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\Queries;

interface ActiveServiceReferenceReadInterface
{
    /** @return list<string> */
    public function forTenant(string $tenantId): array;
}
