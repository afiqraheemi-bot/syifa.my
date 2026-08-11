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
        $hasWebsites = $this->hasTable('websites');
        $hasPublicHosts = $hasWebsites && $this->hasTable('website_public_hosts');
        $query = $this->connection
            ->table('website_designer_assignments as assignment')
            ->join('onboarding_jobs as job', function ($join): void {
                $join->on('job.id', '=', 'assignment.onboarding_job_id')
                    ->on('job.tenant_id', '=', 'assignment.tenant_id');
            });
        if ($hasWebsites) {
            $query->join('websites as website', function ($join): void {
                $join->on('website.id', '=', 'job.website_id')
                    ->on('website.tenant_id', '=', 'job.tenant_id');
            });
        }
        if ($hasPublicHosts) {
            $query->leftJoin('website_public_hosts as public_host', function ($join): void {
                $join->on('public_host.website_id', '=', 'job.website_id')
                    ->on('public_host.tenant_id', '=', 'job.tenant_id')
                    ->where('public_host.is_primary', true)
                    ->whereNull('public_host.inactivated_at');
            });
        }
        $query
            ->where('assignment.platform_identity_id', $platformIdentityId)
            ->where('assignment.assignment_status', 'active');

        if ($status !== null) {
            $query->where('job.status', $status);
        }
        if ($search !== null) {
            $query->where(static function ($query) use ($search, $hasWebsites, $hasPublicHosts): void {
                $query->where('job.id', 'ilike', '%'.$search.'%')
                    ->orWhere('job.tenant_id', 'ilike', '%'.$search.'%')
                    ->orWhere('job.website_id', 'ilike', '%'.$search.'%');
                if ($hasWebsites) {
                    $query->orWhere('website.clinic_name', 'ilike', '%'.$search.'%');
                }
                if ($hasPublicHosts) {
                    $query->orWhere('public_host.normalized_host', 'ilike', '%'.$search.'%');
                }
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

    private function hasTable(string $table): bool
    {
        return $this->connection
            ->table('information_schema.tables')
            ->whereRaw('table_schema = current_schema()')
            ->where('table_name', $table)
            ->exists();
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
        $tasks = $this->connection->table('onboarding_tasks')
            ->where('tenant_id', (string) $row->tenant_id)
            ->where('onboarding_job_id', (string) $row->id)
            ->orderBy('sort_order')
            ->get()
            ->map(static fn (object $task): array => [
                'id' => (string) $task->id,
                'key' => (string) $task->task_key,
                'title' => (string) $task->title,
                'responsibility' => (string) $task->responsibility,
                'status' => (string) $task->status,
                'mandatory' => (bool) $task->mandatory,
                'blocking' => (bool) $task->blocking,
                'dueAt' => $task->due_at === null ? null : (string) $task->due_at,
                'evidenceReference' => $task->evidence_reference === null ? null : (string) $task->evidence_reference,
                'note' => $task->note === null ? null : (string) $task->note,
                'completedAt' => $task->completed_at === null ? null : (string) $task->completed_at,
            ])
            ->all();

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
            array_values($tasks),
        );
    }
}
