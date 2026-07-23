<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\Website;

use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardSectionProjection;
use App\Support\Dashboard\Application\DashboardSectionProviderInterface;

final readonly class WebsiteQuickActionsProvider implements DashboardSectionProviderInterface
{
    public function provide(AuthorizationContext $context): DashboardSectionProjection
    {
        return new DashboardSectionProjection('quickActions', [
            ['key' => 'edit', 'label' => 'Edit website', 'description' => 'Website editing is not available yet.', 'href' => null, 'available' => false],
            ['key' => 'publish', 'label' => 'Publish website', 'description' => 'Publishing is not available yet.', 'href' => null, 'available' => false],
            ['key' => 'domain', 'label' => 'Manage domain', 'description' => 'Domain management is not available yet.', 'href' => null, 'available' => false],
        ]);
    }
}
