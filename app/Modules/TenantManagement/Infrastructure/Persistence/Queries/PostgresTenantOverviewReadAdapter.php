<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Infrastructure\Persistence\Queries;

use App\Modules\TenantManagement\Contracts\TenantOverview\TenantOverviewData;
use App\Modules\TenantManagement\Contracts\TenantOverview\TenantOverviewReadInterface;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresTenantOverviewReadAdapter implements TenantOverviewReadInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function list(?string $status, ?string $cursor, int $limit, ?string $search): array
    {
        $query = $this->connection->table('tenants')
            ->leftJoin('subscriptions', 'subscriptions.tenant_id', '=', 'tenants.id')
            ->leftJoin('websites', 'websites.tenant_id', '=', 'tenants.id')
            ->leftJoin('clinic_owner_authorities', static function ($join): void {
                $join->on('clinic_owner_authorities.tenant_id', '=', 'tenants.id')
                    ->where('clinic_owner_authorities.authority_status', 'active');
            })
            ->select([
                'tenants.id as tenant_id',
                'websites.clinic_name',
                'clinic_owner_authorities.name as owner_name',
                'clinic_owner_authorities.email as owner_email',
                'tenants.status as tenant_status',
                'subscriptions.status as subscription_status',
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
                    ->orWhere('clinic_owner_authorities.email', 'ilike', '%'.$search.'%');
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
            ))
            ->all());
    }
}
