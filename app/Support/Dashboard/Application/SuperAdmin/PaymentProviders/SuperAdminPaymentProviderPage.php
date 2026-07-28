<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\SuperAdmin\PaymentProviders;

use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardNavigationItem;
use App\Support\Dashboard\Application\DashboardPageView;
use LogicException;

final readonly class SuperAdminPaymentProviderPage
{
    public function fromTrustedContext(mixed $context): DashboardPageView
    {
        if (! $context instanceof AuthorizationContext) {
            throw new LogicException('Super Admin dashboard context was not established.');
        }

        return new DashboardPageView('SubscriptionBilling/PaymentProviders/SuperAdminPaymentProviders', [
            'navigation' => [
                (new DashboardNavigationItem('dashboard', 'Dashboard', route('dashboard'), false))->toArray(),
                (new DashboardNavigationItem('tenants', 'Tenants', route('dashboard.tenants'), false))->toArray(),
                (new DashboardNavigationItem('billing', 'Billing', route('dashboard.billing'), false))->toArray(),
                (new DashboardNavigationItem('commercial', 'Commercial', route('dashboard.commercial'), false))->toArray(),
                (new DashboardNavigationItem('payment-providers', 'Payment Providers', route('dashboard.payment-providers'), true))->toArray(),
            ],
            'breadcrumbs' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')],
                ['key' => 'payment-providers', 'label' => 'Payment Providers'],
            ],
            'pageTitle' => 'Payment Providers',
            'pageDescription' => 'Monitor readiness and manage provider availability.',
            'identityName' => $context->name,
            'contextLabel' => 'Super Admin workspace',
            'providerEndpoints' => [
                'index' => route('payment-providers.index'),
                'health' => route('payment-providers.health'),
            ],
        ]);
    }
}
