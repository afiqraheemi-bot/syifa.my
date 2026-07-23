<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Contracts\Dashboard;

interface WebsiteDesignerDashboardReadInterface
{
    public function forPlatformIdentity(string $platformIdentityId): WebsiteDesignerDashboardData;

    /**
     * @return list<WebsiteDesignerQueueJobData>
     */
    public function queue(
        string $platformIdentityId,
        ?string $status,
        ?string $cursor,
        int $limit,
        ?string $search,
    ): array;

    public function detail(
        string $platformIdentityId,
        string $onboardingJobId,
    ): ?WebsiteDesignerJobDetailData;
}
