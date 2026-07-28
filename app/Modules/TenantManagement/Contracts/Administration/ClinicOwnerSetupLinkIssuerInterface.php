<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\Administration;

interface ClinicOwnerSetupLinkIssuerInterface
{
    public function issue(string $tenantId, string $email): bool;
}
