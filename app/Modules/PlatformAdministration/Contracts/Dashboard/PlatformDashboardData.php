<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\Dashboard;

final readonly class PlatformDashboardData
{
    /** @param list<PlatformDashboardActivityData> $recentActivity */
    public function __construct(
        public int $tenants,
        public int $activeSubscriptions,
        public int $activeWebsiteDesigners,
        public int $onboardingPipeline,
        public int $publishedWebsites,
        public int $bookings,
        public bool $platformHealthy,
        public array $recentActivity,
    ) {}
}
