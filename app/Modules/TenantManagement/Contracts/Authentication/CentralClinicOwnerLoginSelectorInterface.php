<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\Authentication;

interface CentralClinicOwnerLoginSelectorInterface
{
    public function selectorFor(string $email): ?string;
}
