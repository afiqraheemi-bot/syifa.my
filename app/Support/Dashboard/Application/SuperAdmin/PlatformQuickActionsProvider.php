<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\SuperAdmin;

use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardSectionProjection;
use App\Support\Dashboard\Application\DashboardSectionProviderInterface;

final readonly class PlatformQuickActionsProvider implements DashboardSectionProviderInterface
{
    public function __construct(
        private ?string $tenantsUrl = null,
        private ?string $billingUrl = null,
        private ?string $commercialUrl = null,
    ) {}

    public function provide(AuthorizationContext $context): DashboardSectionProjection
    {
        return new DashboardSectionProjection('quickActions', [
            ['key' => 'tenants', 'label' => 'Manage tenants', 'description' => 'Review tenant, subscription, website, and assignment status.', 'href' => $this->tenantsUrl ?? route('dashboard.tenants'), 'available' => true],
            ['key' => 'billing', 'label' => 'Billing & subscriptions', 'description' => 'Review subscriptions, payments, and billing health.', 'href' => $this->billingUrl ?? route('dashboard.billing'), 'available' => true],
            ['key' => 'commercial', 'label' => 'Manage commercial', 'description' => 'Manage plans, annual offerings, pricing, and features.', 'href' => $this->commercialUrl ?? route('dashboard.commercial'), 'available' => true],
        ]);
    }
}
