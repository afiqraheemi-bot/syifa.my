<?php

declare(strict_types=1);

namespace App\Support\Provisioning\Infrastructure;

use App\Modules\WebsiteBuilder\Contracts\Publication\WebsitePublicationApprovalReadInterface;
use Illuminate\Database\ConnectionInterface;

final readonly class OnboardingWebsitePublicationApprovalAdapter implements WebsitePublicationApprovalReadInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function isApproved(
        string $tenantId,
        string $websiteId,
        int $websiteVersion,
        int $draftVersion,
    ): bool {
        return $this->connection->table('onboarding_website_approvals as approval')
            ->join('onboarding_jobs as job', function ($join): void {
                $join->on('job.id', '=', 'approval.onboarding_job_id')
                    ->on('job.tenant_id', '=', 'approval.tenant_id')
                    ->on('job.website_id', '=', 'approval.website_id');
            })
            ->where('approval.tenant_id', $tenantId)
            ->where('approval.website_id', $websiteId)
            ->where('approval.status', 'approved')
            ->where('approval.website_version', $websiteVersion)
            ->where('approval.draft_version', $draftVersion)
            ->where('job.status', 'ready_for_launch')
            ->exists();
    }
}
