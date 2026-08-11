<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\SuperAdmin\Tenants;

use App\Modules\TenantManagement\Contracts\TenantOverview\TenantOverviewData;
use App\Modules\TenantManagement\Contracts\TenantOverview\TenantOverviewReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardSectionProjection;
use DateTimeImmutable;
use Throwable;

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
        $summary = $this->tenants->summary();

        return new DashboardSectionProjection('tenantOverview', [
            'summary' => [
                ['key' => 'total', 'label' => 'Total tenants', 'value' => $summary->total, 'tone' => 'neutral'],
                ['key' => 'operational', 'label' => 'Operational', 'value' => $summary->operational, 'tone' => 'success'],
                ['key' => 'provisioning', 'label' => 'Provisioning', 'value' => $summary->provisioning, 'tone' => 'info'],
                ['key' => 'attention', 'label' => 'Needs attention', 'value' => $summary->needsAttention, 'tone' => 'warning'],
            ],
            'items' => array_map(static fn (TenantOverviewData $tenant): array => [
                'id' => $tenant->tenantId,
                'reference' => self::reference($tenant->tenantId),
                'clinicName' => $tenant->clinicName ?? 'Clinic name unavailable',
                'ownerName' => $tenant->ownerName ?? 'Owner unavailable',
                'ownerEmail' => $tenant->ownerEmail ?? 'Email unavailable',
                'status' => $tenant->tenantStatus,
                'statusLabel' => self::label($tenant->tenantStatus),
                'statusTone' => self::tenantStatusTone($tenant->tenantStatus),
                'provisionedAtLabel' => self::dateLabel($tenant->provisionedAt),
                'subscriptionStatus' => $tenant->subscriptionStatus ?? 'not_available',
                'subscriptionStatusLabel' => $tenant->subscriptionStatus === null
                    ? 'Not available'
                    : self::label($tenant->subscriptionStatus),
                'subscriptionHref' => $tenant->subscriptionId === null
                    ? null
                    : route('dashboard.billing.subscriptions.show', ['subscriptionId' => $tenant->subscriptionId]),
                'websitePublicationStatus' => $tenant->websitePublished ? 'Published' : 'Not published',
                'websiteLifecycleLabel' => $tenant->websiteLifecycle === null
                    ? 'Website unavailable'
                    : self::label($tenant->websiteLifecycle),
                'publicHost' => $tenant->publicHost ?? 'Not reserved',
                'publicHostStatus' => self::publicHostStatus($tenant),
                'publicHostStatusTone' => self::publicHostStatusTone($tenant),
                'websiteDesigner' => $tenant->websiteDesignerName ?? 'Not assigned',
                'onboardingStatusLabel' => $tenant->onboardingStatus === null
                    ? 'Not available'
                    : self::label($tenant->onboardingStatus),
                'onboardingHref' => $tenant->onboardingJobId === null
                    ? null
                    : route('dashboard.onboarding-management', ['search' => $tenant->onboardingJobId]),
                'attention' => self::attention($tenant),
            ], $visible),
            'search' => [
                'action' => route('dashboard.tenants'),
                'value' => $criteria->search,
                'placeholder' => 'Clinic, owner, email, host or ID',
            ],
            'resetHref' => route('dashboard.tenants'),
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

    private static function reference(string $id): string
    {
        return strtoupper(substr($id, 0, 8));
    }

    private static function label(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }

    private static function tenantStatusTone(string $status): string
    {
        return match ($status) {
            'active', 'reactivated' => 'success',
            'provisioning' => 'info',
            'suspended', 'offboarding' => 'warning',
            'deleted', 'anonymized' => 'danger',
            default => 'neutral',
        };
    }

    private static function publicHostStatus(TenantOverviewData $tenant): string
    {
        if ($tenant->publicHost === null) {
            return 'Address missing';
        }

        if (! $tenant->publicHostActive) {
            return 'Reserved';
        }

        return $tenant->websitePublished ? 'Live address' : 'Address active';
    }

    private static function publicHostStatusTone(TenantOverviewData $tenant): string
    {
        if ($tenant->publicHost === null) {
            return 'danger';
        }

        return $tenant->publicHostActive && $tenant->websitePublished ? 'success' : 'warning';
    }

    /** @return list<string> */
    private static function attention(TenantOverviewData $tenant): array
    {
        $attention = [];
        if ($tenant->ownerEmail === null) {
            $attention[] = 'Clinic Owner authority missing';
        }
        if ($tenant->subscriptionId === null) {
            $attention[] = 'Subscription missing';
        }
        if ($tenant->websiteId === null) {
            $attention[] = 'Website missing';
        }
        if ($tenant->publicHost === null) {
            $attention[] = 'Public Website address missing';
        }

        return $attention;
    }

    private static function dateLabel(?string $value): string
    {
        if ($value === null) {
            return 'Not recorded';
        }

        try {
            return (new DateTimeImmutable($value))->format('j M Y, g:i A');
        } catch (Throwable) {
            return 'Not recorded';
        }
    }
}
