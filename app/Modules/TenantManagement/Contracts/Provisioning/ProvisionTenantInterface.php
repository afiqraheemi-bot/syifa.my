<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\Provisioning;

interface ProvisionTenantInterface
{
    public function execute(ProvisionTenantCommand $command): ProvisionedTenantData;
}
