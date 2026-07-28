<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Infrastructure\Queries;

use App\Modules\Onboarding\Contracts\Administration\SuperAdminOnboardingReadInterface;
use App\Modules\Onboarding\Contracts\Administration\WebsiteDesignerEligibilityInterface;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresSuperAdminOnboardingAdapter implements SuperAdminOnboardingReadInterface, WebsiteDesignerEligibilityInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function overview(?string $status, ?string $search): array
    {
        $jobs = $this->connection->table('onboarding_jobs as job')
            ->join('websites as website', function ($join): void {
                $join->on('website.id', '=', 'job.website_id')
                    ->on('website.tenant_id', '=', 'job.tenant_id');
            })
            ->leftJoin('website_designer_assignments as assignment', function ($join): void {
                $join->on('assignment.onboarding_job_id', '=', 'job.id')
                    ->on('assignment.tenant_id', '=', 'job.tenant_id')
                    ->where('assignment.assignment_status', 'active');
            })
            ->leftJoin('platform_workforce_credentials as designer', 'designer.platform_identity_id', '=', 'assignment.platform_identity_id')
            ->when($status !== null, static fn ($query) => $query->where('job.status', $status))
            ->when($search !== null, static function ($query) use ($search): void {
                $query->where(static function ($query) use ($search): void {
                    $query->where('website.clinic_name', 'ilike', '%'.$search.'%')
                        ->orWhere('job.id', 'ilike', '%'.$search.'%');
                });
            })
            ->orderByDesc('job.updated_at')
            ->limit(100)
            ->get([
                'job.id',
                'job.tenant_id',
                'job.website_id',
                'job.status',
                'job.version',
                'job.updated_at',
                'website.clinic_name',
                'assignment.id as assignment_id',
                'assignment.platform_identity_id as designer_id',
                'designer.name as designer_name',
            ])
            ->map(static fn (object $row): array => [
                'id' => (string) $row->id,
                'tenantId' => (string) $row->tenant_id,
                'websiteId' => (string) $row->website_id,
                'clinicName' => (string) $row->clinic_name,
                'status' => (string) $row->status,
                'version' => (int) $row->version,
                'updatedAt' => (string) $row->updated_at,
                'assignmentId' => $row->assignment_id === null ? null : (string) $row->assignment_id,
                'designerId' => $row->designer_id === null ? null : (string) $row->designer_id,
                'designerName' => $row->designer_name === null ? null : (string) $row->designer_name,
            ])
            ->all();

        $designers = $this->connection->table('platform_workforce_credentials')
            ->where('role', 'website_designer')
            ->where('account_status', 'active')
            ->where('email_verification_status', 'verified')
            ->orderBy('name')
            ->get(['platform_identity_id', 'name', 'normalized_email'])
            ->map(static fn (object $row): array => [
                'id' => (string) $row->platform_identity_id,
                'name' => (string) $row->name,
                'email' => (string) $row->normalized_email,
            ])
            ->all();

        return ['jobs' => array_values($jobs), 'designers' => array_values($designers)];
    }

    public function isEligible(string $platformIdentityId): bool
    {
        return $this->connection->table('platform_workforce_credentials')
            ->where('platform_identity_id', $platformIdentityId)
            ->where('role', 'website_designer')
            ->where('account_status', 'active')
            ->where('email_verification_status', 'verified')
            ->exists();
    }
}
