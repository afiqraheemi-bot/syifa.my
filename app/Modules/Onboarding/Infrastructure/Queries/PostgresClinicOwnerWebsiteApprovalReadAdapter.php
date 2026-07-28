<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Infrastructure\Queries;

use App\Modules\Onboarding\Contracts\WebsiteApproval\ClinicOwnerWebsiteApprovalReadInterface;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresClinicOwnerWebsiteApprovalReadAdapter implements ClinicOwnerWebsiteApprovalReadInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function forTenant(string $tenantId): ?array
    {
        $row = $this->connection->table('onboarding_jobs as job')
            ->leftJoin('onboarding_website_approvals as approval', function ($join): void {
                $join->on('approval.onboarding_job_id', '=', 'job.id')
                    ->on('approval.tenant_id', '=', 'job.tenant_id');
            })
            ->where('job.tenant_id', $tenantId)
            ->orderByDesc('job.created_at')
            ->first([
                'job.id as job_id',
                'job.version as job_version',
                'job.status as job_status',
                'approval.id as approval_id',
                'approval.status as approval_status',
                'approval.correction_note',
                'approval.requested_at',
                'approval.decided_at',
            ]);

        return $row === null ? null : [
            'jobId' => (string) $row->job_id,
            'jobVersion' => (int) $row->job_version,
            'jobStatus' => (string) $row->job_status,
            'approvalId' => $row->approval_id === null ? null : (string) $row->approval_id,
            'approvalStatus' => $row->approval_status === null ? null : (string) $row->approval_status,
            'correctionNote' => $row->correction_note === null ? null : (string) $row->correction_note,
            'requestedAt' => $row->requested_at === null ? null : (string) $row->requested_at,
            'decidedAt' => $row->decided_at === null ? null : (string) $row->decided_at,
        ];
    }
}
