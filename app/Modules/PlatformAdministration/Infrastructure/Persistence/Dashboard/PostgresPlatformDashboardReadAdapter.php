<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Infrastructure\Persistence\Dashboard;

use App\Modules\PlatformAdministration\Contracts\Dashboard\PlatformDashboardActivityData;
use App\Modules\PlatformAdministration\Contracts\Dashboard\PlatformDashboardData;
use App\Modules\PlatformAdministration\Contracts\Dashboard\PlatformDashboardReadInterface;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresPlatformDashboardReadAdapter implements PlatformDashboardReadInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function overview(): PlatformDashboardData
    {
        $counts = $this->connection->selectOne(<<<'SQL'
            SELECT
                (SELECT COUNT(*) FROM tenants) AS tenants,
                (SELECT COUNT(*) FROM subscriptions WHERE status = 'active') AS active_subscriptions,
                (
                    SELECT COUNT(*)
                    FROM platform_workforce_credentials
                    WHERE role = 'website_designer' AND account_status = 'active'
                ) AS active_website_designers,
                (
                    SELECT COUNT(*)
                    FROM onboarding_jobs
                    WHERE status IN (
                        'awaiting_inputs', 'assigned', 'in_progress', 'blocked',
                        'in_review', 'correction_required', 'ready_for_launch', 'reopened'
                    )
                ) AS onboarding_pipeline,
                (SELECT COUNT(DISTINCT website_id) FROM website_published_snapshots) AS published_websites,
                (SELECT COUNT(*) FROM bookings) AS bookings
            SQL);

        return new PlatformDashboardData(
            tenants: (int) ($counts->tenants ?? 0),
            activeSubscriptions: (int) ($counts->active_subscriptions ?? 0),
            activeWebsiteDesigners: (int) ($counts->active_website_designers ?? 0),
            onboardingPipeline: (int) ($counts->onboarding_pipeline ?? 0),
            publishedWebsites: (int) ($counts->published_websites ?? 0),
            bookings: (int) ($counts->bookings ?? 0),
            platformHealthy: true,
            recentActivity: array_values($this->connection->table('audit_entries')
                ->select(['audit_entry_id', 'action', 'outcome', 'occurred_at'])
                ->orderByDesc('occurred_at')
                ->limit(5)
                ->get()
                ->map(static fn (object $row): PlatformDashboardActivityData => new PlatformDashboardActivityData(
                    (string) $row->audit_entry_id,
                    (string) $row->action,
                    (string) $row->outcome,
                    new DateTimeImmutable((string) $row->occurred_at),
                ))
                ->all()),
        );
    }
}
