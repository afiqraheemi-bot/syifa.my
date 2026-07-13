<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\TenantRouting;

interface TenantAdminRoutingLookupInterface
{
    public function resolve(string $normalizedRoutingLabel): ?TenantAdminRoutingData;
}
