<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\TenantContext;

final readonly class TenantContextAssignmentData
{
    public function __construct(
        public string $assignmentId,
        public string $onboardingJobId,
        public string $platformIdentityId,
        public string $tenantId,
    ) {}
}
