<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application;

use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\SuperAdmin\SuperAdminDashboardOverviewProvider;
use App\Support\Dashboard\Application\WebsiteDesigner\WebsiteDesignerDashboardOverviewProvider;
use App\Support\Identity\ActorType;
use LogicException;

final readonly class AuthenticatedDashboardPage
{
    public function __construct(
        private ClinicOwnerDashboardOverviewProvider $clinicOwnerOverview,
        private WebsiteDesignerDashboardOverviewProvider $websiteDesignerOverview,
        private SuperAdminDashboardOverviewProvider $superAdminOverview,
    ) {}

    /**
     * Converts the trusted context established by authorization middleware
     * into presentation data. Navigation is deliberately shared in this
     * increment; later role-specific items must be pre-authorized here, never
     * filtered by Vue components.
     */
    public function fromTrustedContext(mixed $context): DashboardPageView
    {
        if (! $context instanceof AuthorizationContext) {
            throw new LogicException('Authenticated dashboard context was not established.');
        }

        $props = [
            'navigation' => [
                (new DashboardNavigationItem(
                    'dashboard',
                    'Dashboard',
                    route('dashboard'),
                    true,
                ))->toArray(),
            ],
            'breadcrumbs' => [
                ['key' => 'dashboard', 'label' => 'Dashboard'],
            ],
            'pageTitle' => 'Dashboard',
            'pageDescription' => 'Your SYIFA.my workspace is ready.',
            'identityName' => $context->name,
            'contextLabel' => 'SYIFA.my workspace',
        ];

        if ($context->actorType === ActorType::ClinicOwner->value && $context->role === 'clinic_owner') {
            $props['contextLabel'] = 'Clinic Owner workspace';
            $props['navigation'] = ClinicOwnerDashboardNavigation::items('dashboard');

            return new DashboardPageView(
                'TenantManagement/Dashboard/ClinicOwnerDashboardOverview',
                [...$props, ...$this->clinicOwnerOverview->for($context)],
            );
        }

        if ($context->actorType === ActorType::PlatformIdentity->value && $context->role === 'website_designer') {
            $props['contextLabel'] = 'Website Designer workspace';
            $props['navigation'] = WebsiteDesignerDashboardNavigation::items('dashboard');

            return new DashboardPageView(
                'PlatformAdministration/Dashboard/WebsiteDesignerDashboardOverview',
                [...$props, ...$this->websiteDesignerOverview->for($context)],
            );
        }

        if ($context->actorType === ActorType::PlatformIdentity->value && $context->role === 'super_admin') {
            $props['contextLabel'] = 'Super Admin workspace';
            $props['navigation'][] = (new DashboardNavigationItem(
                'registrations',
                'Registrations',
                route('dashboard.registrations'),
                false,
            ))->toArray();
            $props['navigation'][] = (new DashboardNavigationItem(
                'tenants',
                'Tenants',
                route('dashboard.tenants'),
                false,
            ))->toArray();
            $props['navigation'][] = (new DashboardNavigationItem(
                'onboarding-management',
                'Onboarding',
                route('dashboard.onboarding-management'),
                false,
            ))->toArray();
            $props['navigation'][] = (new DashboardNavigationItem(
                'billing',
                'Billing',
                route('dashboard.billing'),
                false,
            ))->toArray();
            $props['navigation'][] = (new DashboardNavigationItem(
                'commercial',
                'Commercial',
                route('dashboard.commercial'),
                false,
            ))->toArray();
            $props['navigation'][] = (new DashboardNavigationItem(
                'payment-providers',
                'Payment Providers',
                route('dashboard.payment-providers'),
                false,
            ))->toArray();
            $props['navigation'][] = (new DashboardNavigationItem(
                'notifications',
                'Notifications',
                route('dashboard.notifications'),
                false,
            ))->toArray();
            $props['navigation'][] = (new DashboardNavigationItem(
                'audit',
                'Audit Activity',
                route('dashboard.audit'),
                false,
            ))->toArray();
            $props['navigation'][] = (new DashboardNavigationItem(
                'reports',
                'Reports',
                route('dashboard.reports'),
                false,
            ))->toArray();

            return new DashboardPageView(
                'PlatformAdministration/Dashboard/SuperAdminDashboardOverview',
                [...$props, ...$this->superAdminOverview->for($context)],
            );
        }

        return new DashboardPageView('Shared/Dashboard/AuthenticatedDashboard', $props);
    }
}
