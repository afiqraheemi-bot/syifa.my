<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\SuperAdmin\Tenants;

use App\Modules\TenantManagement\Contracts\TenantOverview\TenantOverviewData;
use App\Modules\TenantManagement\Contracts\TenantOverview\TenantOverviewReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardSectionProjection;

final readonly class TenantOverviewProvider
{
    public function __construct(private TenantOverviewReadInterface $tenants) {}

    public function provide(AuthorizationContext $context, TenantOverviewCriteria $criteria): DashboardSectionProjection
    {
        $rows = $this->tenants->list(
            $criteria->status, $criteria->cursor, $criteria->perPage + 1, $criteria->search,
        );
        $hasMore = count($rows) > $criteria->perPage;
        $visible = array_slice($rows, 0, $criteria->perPage);
        $last = $visible === [] ? null : $visible[array_key_last($visible)];
        $nextCursor = $hasMore && $last instanceof TenantOverviewData ? $last->tenantId : null;

        return new DashboardSectionProjection('tenantOverview', [
            'items' => array_map(static fn (TenantOverviewData $tenant): array => [
                'id' => $tenant->tenantId,
                'clinicName' => $tenant->clinicName ?? 'Clinic name unavailable',
                'ownerName' => $tenant->ownerName ?? 'Owner unavailable',
                'ownerEmail' => $tenant->ownerEmail ?? 'Email unavailable',
                'status' => $tenant->tenantStatus,
                'statusLabel' => ucfirst($tenant->tenantStatus),
                'subscriptionStatus' => $tenant->subscriptionStatus ?? 'not_available',
                'subscriptionStatusLabel' => $tenant->subscriptionStatus === null
                    ? 'Not available'
                    : ucwords(str_replace('_', ' ', $tenant->subscriptionStatus)),
                'websitePublicationStatus' => $tenant->websitePublished ? 'Published' : 'Not published',
                'websiteDesigner' => $tenant->websiteDesignerName ?? 'Not assigned',
            ], $visible),
            'search' => [
                'action' => route('dashboard.tenants'),
                'value' => $criteria->search,
                'placeholder' => 'Search clinic, owner or email',
            ],
            'statusFilter' => ['value' => $criteria->status, 'options' => TenantOverviewCriteria::statusOptions()],
            'pagination' => [
                'hasMore' => $hasMore,
                'perPage' => $criteria->perPage,
                'nextHref' => $nextCursor === null ? null : route('dashboard.tenants', array_filter([
                    'search' => $criteria->search,
                    'status' => $criteria->status,
                    'cursor' => $nextCursor,
                    'per_page' => $criteria->perPage,
                ], static fn (string|int|null $value): bool => $value !== null)),
            ],
        ]);
    }
}
