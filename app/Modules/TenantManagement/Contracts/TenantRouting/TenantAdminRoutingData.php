<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\TenantRouting;

final readonly class TenantAdminRoutingData
{
    public function __construct(
        public string $tenantId,
        public string $routingLabel,
        public string $lifecycleStatus,
    ) {}
}
