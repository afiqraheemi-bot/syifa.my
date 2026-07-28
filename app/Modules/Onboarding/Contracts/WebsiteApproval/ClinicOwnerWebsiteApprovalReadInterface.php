<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Contracts\WebsiteApproval;

interface ClinicOwnerWebsiteApprovalReadInterface
{
    /**
     * @return array{
     *   jobId: string,
     *   jobVersion: int,
     *   jobStatus: string,
     *   approvalId: ?string,
     *   approvalStatus: ?string,
     *   correctionNote: ?string,
     *   requestedAt: ?string,
     *   decidedAt: ?string
     * }|null
     */
    public function forTenant(string $tenantId): ?array;
}
