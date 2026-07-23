<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\WebsiteDesigner;

use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardSectionProjection;
use App\Support\Dashboard\Application\DashboardSectionProviderInterface;

final readonly class WebsiteDesignerQuickActionsProvider implements DashboardSectionProviderInterface
{
    public function provide(AuthorizationContext $context): DashboardSectionProjection
    {
        return new DashboardSectionProjection('quickActions', [
            [
                'key' => 'view-assignments',
                'label' => 'View assignments',
                'description' => 'Assignment management is not available in this increment.',
                'href' => null,
                'available' => false,
            ],
            [
                'key' => 'continue-setup',
                'label' => 'Continue website setup',
                'description' => 'Website editing is not available in this increment.',
                'href' => null,
                'available' => false,
            ],
            [
                'key' => 'review-projects',
                'label' => 'Review projects',
                'description' => 'Review operations are not available in this increment.',
                'href' => null,
                'available' => false,
            ],
        ]);
    }
}
