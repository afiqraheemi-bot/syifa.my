<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application;

use App\Support\Authorization\Application\AuthorizationContext;

final readonly class QuickActionsProvider implements DashboardSectionProviderInterface
{
    public function provide(AuthorizationContext $context): DashboardSectionProjection
    {
        return new DashboardSectionProjection('quickActions', [
            ['key' => 'website', 'label' => 'Manage website', 'description' => 'Website management is not available yet.', 'href' => null, 'available' => false],
            ['key' => 'bookings', 'label' => 'View bookings', 'description' => 'Booking management is not available yet.', 'href' => null, 'available' => false],
            ['key' => 'subscription', 'label' => 'View subscription', 'description' => 'Subscription management is not available yet.', 'href' => null, 'available' => false],
        ]);
    }
}
