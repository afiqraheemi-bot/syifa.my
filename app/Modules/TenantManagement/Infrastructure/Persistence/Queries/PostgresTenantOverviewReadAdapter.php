<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Infrastructure\Persistence\Queries;

use App\Modules\TenantManagement\Contracts\TenantOverview\TenantOverviewData;
use App\Modules\TenantManagement\Contracts\TenantOverview\TenantOverviewReadInterface;
use App\Modules\TenantManagement\Contracts\TenantOverview\TenantOverviewSummaryData;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresTenantOverviewReadAdapter implements TenantOverviewReadInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function summary(): TenantOverviewSummaryData
    {
        $row = $this->connection->table('tenants')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("COUNT(*) FILTER (WHERE status IN ('active', 'reactivated')) AS operational")
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'provisioning') AS provisioning")
            ->selectRaw("COUNT(*) FILTER (WHERE status IN ('suspended', 'offboarding', 'deleted', 'anonymized')) AS needs_attention")
            ->first();

        return new TenantOverviewSummaryData(
            (int) ($row->total ?? 0),
            (int) ($row->operational ?? 0),
            (int) ($row->provisioning ?? 0),
            (int) ($row->needs_attention ?? 0),
        );
    }

    public function list(?string $status, ?string $cursor, int $limit, ?string $search): array
    {
        $query = $this->connection->table('tenants')
            ->leftJoin('subscriptions', 'subscriptions.tenant_id', '=', 'tenants.id')
            ->leftJoin('websites', 'websites.tenant_id', '=', 'tenants.id')
            ->leftJoin('website_public_hosts', static function ($join): void {
                $join->on('website_public_hosts.website_id', '=', 'websites.id')
                    ->where('website_public_hosts.is_primary', true)
                    ->whereNull('website_public_hosts.inactivated_at');
            })
            ->leftJoin('clinic_owner_authorities', static function ($join): void {
                $join->on('clinic_owner_authorities.tenant_id', '=', 'tenants.id')
                    ->where('clinic_owner_authorities.authority_status', 'active');
            })
            ->select([
                'tenants.id as tenant_id',
                'tenants.provisioned_at',
                'websites.clinic_name',
                'websites.id as website_id',
                'websites.lifecycle as website_lifecycle',
                'clinic_owner_authorities.name as owner_name',
                'clinic_owner_authorities.email as owner_email',
                'tenants.status as tenant_status',
                'subscriptions.id as subscription_id',
                'subscriptions.status as subscription_status',
                'website_public_hosts.normalized_host as public_host',
                'website_public_hosts.activated_at as public_host_activated_at',
            ])
            ->selectRaw(<<<'SQL'
                EXISTS (
                    SELECT 1
                    FROM websites
                    INNER JOIN website_published_snapshots
                        ON website_published_snapshots.website_id = websites.id
                    WHERE websites.tenant_id = tenants.id
                ) AS website_published
                SQL)
            ->selectSub(
                $this->connection->table('website_designer_assignments')
                    ->join(
                        'platform_workforce_credentials',
                        'platform_workforce_credentials.platform_identity_id',
                        '=',
                        'website_designer_assignments.platform_identity_id',
                    )
                    ->select('platform_workforce_credentials.name')
                    ->whereColumn('tenant_id', 'tenants.id')
                    ->where('assignment_status', 'active')
                    ->orderByDesc('assigned_at')
                    ->limit(1),
                'website_designer_name',
            )
            ->selectSub(
                $this->connection->table('onboarding_jobs')
                    ->select('id')
                    ->whereColumn('tenant_id', 'tenants.id')
                    ->orderByDesc('updated_at')
                    ->limit(1),
                'onboarding_job_id',
            )
            ->selectSub(
                $this->connection->table('onboarding_jobs')
                    ->select('status')
                    ->whereColumn('tenant_id', 'tenants.id')
                    ->orderByDesc('updated_at')
                    ->limit(1),
                'onboarding_status',
            );

        if ($status !== null) {
            $query->where('tenants.status', $status);
        }
        if ($cursor !== null) {
            $query->where('tenants.id', '>', $cursor);
        }
        if ($search !== null) {
            $query->where(static function ($query) use ($search): void {
                $query
                    ->where('websites.clinic_name', 'ilike', '%'.$search.'%')
                    ->orWhere('clinic_owner_authorities.name', 'ilike', '%'.$search.'%')
                    ->orWhere('clinic_owner_authorities.email', 'ilike', '%'.$search.'%')
                    ->orWhere('website_public_hosts.normalized_host', 'ilike', '%'.$search.'%')
                    ->orWhereRaw('CAST(tenants.id AS TEXT) ILIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('CAST(subscriptions.id AS TEXT) ILIKE ?', ['%'.$search.'%']);
            });
        }

        return array_values($query
            ->orderBy('tenants.id')
            ->limit($limit)
            ->get()
            ->map(static fn (object $row): TenantOverviewData => new TenantOverviewData(
                (string) $row->tenant_id,
                $row->clinic_name === null ? null : (string) $row->clinic_name,
                $row->owner_name === null ? null : (string) $row->owner_name,
                $row->owner_email === null ? null : (string) $row->owner_email,
                (string) $row->tenant_status,
                $row->subscription_status === null ? null : (string) $row->subscription_status,
                (bool) $row->website_published,
                $row->website_designer_name === null ? null : (string) $row->website_designer_name,
                $row->subscription_id === null ? null : (string) $row->subscription_id,
                $row->website_id === null ? null : (string) $row->website_id,
                $row->website_lifecycle === null ? null : (string) $row->website_lifecycle,
                $row->public_host === null ? null : (string) $row->public_host,
                $row->public_host_activated_at !== null,
                $row->onboarding_job_id === null ? null : (string) $row->onboarding_job_id,
                $row->onboarding_status === null ? null : (string) $row->onboarding_status,
                $row->provisioned_at === null ? null : (string) $row->provisioned_at,
            ))
            ->all());
    }
}
