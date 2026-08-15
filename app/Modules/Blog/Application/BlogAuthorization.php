<?php

declare(strict_types=1);

namespace App\Modules\Blog\Application;

use App\Modules\SubscriptionBilling\Contracts\Entitlements\SubscriptionEntitlementLookupInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use Illuminate\Database\ConnectionInterface;

final readonly class BlogAuthorization
{
    public const string CAPABILITY = 'website.blog.manage';

    public function __construct(
        private SubscriptionEntitlementLookupInterface $entitlements,
        private ConnectionInterface $connection,
    ) {}

    public function entitled(string $tenantId): bool
    {
        return $this->entitlements->hasCapability($tenantId, self::CAPABILITY, gmdate('Y-m-d\TH:i:s\Z'));
    }

    public function authorize(AuthorizationContext $actor, string $tenantId, string $websiteId, bool $mutation = true): void
    {
        if ($actor->role === 'super_admin') {
            return;
        }
        if (! $this->entitled($tenantId)) {
            abort(403, 'Blog klinik hanya tersedia dalam pakej Syifa Pro. Naik taraf untuk mengurus dan menerbitkan artikel.');
        }
        if ($actor->role === 'clinic_owner' && hash_equals((string) $actor->tenantId, $tenantId)) {
            return;
        }
        if ($actor->role === 'website_designer' && $this->hasActiveAssignment($actor->identityId, $tenantId, $websiteId)) {
            return;
        }

        abort(403);
    }

    private function hasActiveAssignment(string $identityId, string $tenantId, string $websiteId): bool
    {
        return $this->connection->table('website_designer_assignments as assignment')
            ->join('onboarding_jobs as job', function ($join): void {
                $join->on('job.id', '=', 'assignment.onboarding_job_id')->on('job.tenant_id', '=', 'assignment.tenant_id');
            })
            ->where('assignment.platform_identity_id', $identityId)
            ->where('assignment.tenant_id', $tenantId)
            ->where('assignment.assignment_status', 'active')
            ->where('job.website_id', $websiteId)
            ->exists();
    }
}
