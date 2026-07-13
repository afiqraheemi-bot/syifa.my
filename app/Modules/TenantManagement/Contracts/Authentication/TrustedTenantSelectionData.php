<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\Authentication;

final readonly class TrustedTenantSelectionData
{
    public function __construct(public string $tenantId) {}
}
