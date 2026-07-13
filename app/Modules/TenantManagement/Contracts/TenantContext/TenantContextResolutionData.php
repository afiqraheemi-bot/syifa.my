<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\TenantContext;

final readonly class TenantContextResolutionData
{
    public function __construct(
        public ?string $platformIdentityId,
        public string $tenantId,
        public string $role,
        public ?TenantContextAssignmentData $assignment,
    ) {}
}
