<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\Provisioning;

final readonly class ProvisionedTenantData
{
    public function __construct(
        public string $tenantId,
        public string $status,
        public int $version,
        public bool $alreadyExisted,
    ) {}
}
