<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\SuperAdmin\Billing;

use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardNavigationItem;
use App\Support\Dashboard\Application\DashboardPageView;
use DateTimeImmutable;
use LogicException;

final readonly class SuperAdminBillingOverviewPage
{
    public function __construct(private BillingOverviewProvider $billing) {}

    /** @param array<string, mixed> $query */
    public function fromTrustedContext(mixed $context, array $query): DashboardPageView
    {
        if (! $context instanceof AuthorizationContext) {
            throw new LogicException('Super Admin dashboard context was not established.');
        }

        return new DashboardPageView('SubscriptionBilling/Dashboard/SuperAdminBillingOverview', [
            'navigation' => [
                (new DashboardNavigationItem('dashboard', 'Dashboard', route('dashboard'), false))->toArray(),
                (new DashboardNavigationItem('registrations', 'Registrations', route('dashboard.registrations'), false))->toArray(),
                (new DashboardNavigationItem('tenants', 'Tenants', route('dashboard.tenants'), false))->toArray(),
                (new DashboardNavigationItem('onboarding-management', 'Onboarding', route('dashboard.onboarding-management'), false))->toArray(),
                (new DashboardNavigationItem('billing', 'Billing', route('dashboard.billing'), true))->toArray(),
                (new DashboardNavigationItem('commercial', 'Commercial', route('dashboard.commercial'), false))->toArray(),
                (new DashboardNavigationItem('payment-providers', 'Payment Providers', route('dashboard.payment-providers'), false))->toArray(),
            ],
            'breadcrumbs' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')],
                ['key' => 'billing', 'label' => 'Billing'],
            ],
            'pageTitle' => 'Billing & subscriptions',
            'pageDescription' => 'Review subscription lifecycle, payment status, and billing health.',
            'identityName' => $context->name,
            'contextLabel' => 'Super Admin workspace',
            'billingOverview' => $this->billing->provide(
                $context,
                BillingOverviewCriteria::fromInput($query),
                (new DateTimeImmutable('today'))->format('Y-m-d'),
            )->data,
        ]);
    }
}
