<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\Authentication;

interface TrustedTenantSelectorInterface
{
    public function select(string $selectorReference): ?TrustedTenantSelectionData;
}
