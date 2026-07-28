<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application;

use App\Support\Authorization\Application\AuthorizationContext;

final readonly class QuickActionsProvider implements DashboardSectionProviderInterface
{
    public function provide(AuthorizationContext $context): DashboardSectionProjection
    {
        return new DashboardSectionProjection('quickActions', [
            ['key' => 'website', 'label' => 'Manage website', 'description' => 'Manage your clinic website and content.', 'href' => route('dashboard.website'), 'available' => true],
            ['key' => 'bookings', 'label' => 'View bookings', 'description' => 'Review and manage patient bookings.', 'href' => route('dashboard.bookings'), 'available' => true],
            ['key' => 'subscription', 'label' => 'View subscription', 'description' => 'View your current plan and renewal status.', 'href' => route('dashboard.subscription'), 'available' => true],
        ]);
    }
}
