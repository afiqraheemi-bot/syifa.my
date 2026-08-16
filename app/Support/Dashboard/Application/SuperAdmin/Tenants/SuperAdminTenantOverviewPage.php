<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\SuperAdmin\Tenants;

use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardNavigationItem;
use App\Support\Dashboard\Application\DashboardPageView;
use LogicException;

final readonly class SuperAdminTenantOverviewPage
{
    public function __construct(private TenantOverviewProvider $tenants) {}

    /** @param array<string, mixed> $query */
    public function fromTrustedContext(mixed $context, array $query): DashboardPageView
    {
        if (! $context instanceof AuthorizationContext) {
            throw new LogicException('Super Admin dashboard context was not established.');
        }

        return new DashboardPageView('PlatformAdministration/Tenants/SuperAdminTenantOverview', [
            'navigation' => [
                (new DashboardNavigationItem('dashboard', 'Dashboard', route('dashboard'), false))->toArray(),
                (new DashboardNavigationItem('registrations', 'Registrations', route('dashboard.registrations'), false))->toArray(),
                (new DashboardNavigationItem('tenants', 'Tenants', route('dashboard.tenants'), true))->toArray(),
                (new DashboardNavigationItem('onboarding-management', 'Onboarding', route('dashboard.onboarding-management'), false))->toArray(),
                (new DashboardNavigationItem('billing', 'Billing', route('dashboard.billing'), false))->toArray(),
                (new DashboardNavigationItem('commercial', 'Commercial', route('dashboard.commercial'), false))->toArray(),
                (new DashboardNavigationItem('payment-providers', 'Payment Providers', route('dashboard.payment-providers'), false))->toArray(),
                (new DashboardNavigationItem('syifa-ai-usage', 'SYIFA AI Usage', route('dashboard.syifa-ai-usage'), false))->toArray(),
                (new DashboardNavigationItem('audit', 'Audit Activity', route('dashboard.audit'), false))->toArray(),
            ],
            'breadcrumbs' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')],
                ['key' => 'tenants', 'label' => 'Tenants'],
            ],
            'pageTitle' => 'Tenant management',
            'pageDescription' => 'Monitor every clinic from provisioning through subscription, Website launch, and onboarding.',
            'identityName' => $context->name,
            'contextLabel' => 'Super Admin workspace',
            'tenantOverview' => $this->tenants
                ->provide($context, TenantOverviewCriteria::fromInput($query))
                ->data,
        ]);
    }
}
