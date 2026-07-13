<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\TenantContext;

interface TenantContextResolverInterface
{
    public function resolve(TenantContextResolutionData $resolution): ?TenantContextData;
}
