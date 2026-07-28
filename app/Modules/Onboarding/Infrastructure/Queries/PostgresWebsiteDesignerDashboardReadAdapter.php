<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Infrastructure\Queries;

use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerDashboardData;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerDashboardReadInterface;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerJobDetailData;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerQueueJobData;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerRecentAssignmentData;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresWebsiteDesignerDashboardReadAdapter implements WebsiteDesignerDashboardReadInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function forPlatformIdentity(string $platformIdentityId): WebsiteDesignerDashboardData
    {
        $assignments = $this->connection
            ->table('website_designer_assignments as assignment')
            ->join('onboarding_jobs as job', function ($join): void {
                $join->on('job.id', '=', 'assignment.onboarding_job_id')
                    ->on('job.tenant_id', '=', 'assignment.tenant_id');
            })
            ->where('assignment.platform_identity_id', $platformIdentityId)
            ->select([
                'assignment.id as assignment_id',
                'assignment.onboarding_job_id',
                'assignment.tenant_id',
                'assignment.assignment_status',
                'assignment.end_reason',
                'assignment.assigned_at as assignment_assigned_at',
                'job.status',
            ])
            ->orderByDesc('assignment.assigned_at')
            ->get();

        $active = $assignments->where('assignment_status', 'active');
        $countStatuses = static fn (array $statuses): int => $active
            ->whereIn('status', $statuses)
            ->count();

        return new WebsiteDesignerDashboardData(
            assignedJobs: $active->count(),
            pendingContentCollection: $countStatuses(['awaiting_inputs']),
            websiteSetup: $countStatuses(['assigned', 'in_progress', 'blocked', 'reopened']),
            reviewAndRevision: $countStatuses(['in_review', 'correction_required']),
            readyToPublish: $countStatuses(['ready_for_launch']),
            completedProjects: $assignments
                ->where('assignment_status', 'ended')
                ->where('end_reason', 'onboarding_job_completed')
                ->count(),
            recentAssignments: array_values($assignments
                ->take(5)
                ->map(static fn (object $row): WebsiteDesignerRecentAssignmentData => new WebsiteDesignerRecentAssignmentData(
                    assignmentId: (string) $row->assignment_id,
                    onboardingJobId: (string) $row->onboarding_job_id,
                    tenantId: (string) $row->tenant_id,
                    status: (string) $row->status,
                    assignedAt: new DateTimeImmutable((string) $row->assignment_assigned_at),
                ))
                ->values()
                ->all()),
        );
    }

    public function queue(
        string $platformIdentityId,
        ?string $status,
        ?string $cursor,
        int $limit,
        ?string $search,
    ): array {
        $query = $this->connection
            ->table('website_designer_assignments as assignment')
            ->join('onboarding_jobs as job', function ($join): void {
                $join->on('job.id', '=', 'assignment.onboarding_job_id')
                    ->on('job.tenant_id', '=', 'assignment.tenant_id');
            })
            ->where('assignment.platform_identity_id', $platformIdentityId)
            ->where('assignment.assignment_status', 'active');

        if ($status !== null) {
            $query->where('job.status', $status);
        }
        if ($search !== null) {
            $query->where(static function ($query) use ($search): void {
                $query->where('job.id', 'ilike', '%'.$search.'%')
                    ->orWhere('job.tenant_id', 'ilike', '%'.$search.'%')
                    ->orWhere('job.website_id', 'ilike', '%'.$search.'%');
            });
        }
        if ($cursor !== null) {
            $query->where('assignment.assigned_at', '<', function ($query) use ($cursor, $platformIdentityId): void {
                $query->select('assigned_at')
                    ->from('website_designer_assignments')
                    ->where('id', $cursor)
                    ->where('platform_identity_id', $platformIdentityId)
                    ->limit(1);
            });
        }

        return array_values($query
            ->select([
                'assignment.id as assignment_id',
                'job.id as onboarding_job_id',
                'job.tenant_id',
                'job.website_id',
                'job.status',
                'assignment.assigned_at as assignment_assigned_at',
                'job.updated_at',
            ])
            ->orderByDesc('assignment.assigned_at')
            ->orderByDesc('assignment.id')
            ->limit($limit)
            ->get()
            ->map(static fn (object $row): WebsiteDesignerQueueJobData => new WebsiteDesignerQueueJobData(
                (string) $row->assignment_id,
                (string) $row->onboarding_job_id,
                (string) $row->tenant_id,
                (string) $row->website_id,
                (string) $row->status,
                new DateTimeImmutable((string) $row->assignment_assigned_at),
                new DateTimeImmutable((string) $row->updated_at),
            ))
            ->all());
    }

    public function detail(
        string $platformIdentityId,
        string $onboardingJobId,
    ): ?WebsiteDesignerJobDetailData {
        $row = $this->connection
            ->table('website_designer_assignments as assignment')
            ->join('onboarding_jobs as job', function ($join): void {
                $join->on('job.id', '=', 'assignment.onboarding_job_id')
                    ->on('job.tenant_id', '=', 'assignment.tenant_id');
            })
            ->where('assignment.platform_identity_id', $platformIdentityId)
            ->where('assignment.assignment_status', 'active')
            ->where('job.id', $onboardingJobId)
            ->select([
                'assignment.id as assignment_id',
                'assignment.assigned_at as assignment_assigned_at',
                'job.*',
            ])
            ->first();

        if ($row === null) {
            return null;
        }

        $lifecycle = [];
        foreach ([
            'job_created_at', 'awaiting_inputs_at', 'assigned_at', 'in_progress_at',
            'blocked_at', 'in_review_at', 'correction_required_at',
            'ready_for_launch_at', 'completed_at', 'cancelled_at', 'reopened_at',
        ] as $column) {
            $value = $row->{$column};
            $lifecycle[$column] = $value === null ? null : new DateTimeImmutable((string) $value);
        }

        return new WebsiteDesignerJobDetailData(
            (string) $row->assignment_id,
            (string) $row->id,
            (string) $row->tenant_id,
            (string) $row->website_id,
            (string) $row->status,
            (int) $row->version,
            new DateTimeImmutable((string) $row->assignment_assigned_at),
            new DateTimeImmutable((string) $row->updated_at),
            $lifecycle,
        );
    }
}
