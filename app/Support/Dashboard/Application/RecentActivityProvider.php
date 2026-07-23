<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application;

use App\Support\Authorization\Application\AuthorizationContext;

final readonly class RecentActivityProvider implements DashboardSectionProviderInterface
{
    public function provide(AuthorizationContext $context): DashboardSectionProjection
    {
        return new DashboardSectionProjection('recentActivity', []);
    }
}
