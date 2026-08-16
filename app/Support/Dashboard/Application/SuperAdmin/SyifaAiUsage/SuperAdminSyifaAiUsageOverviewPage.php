<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\SuperAdmin\SyifaAiUsage;

use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardNavigationItem;
use App\Support\Dashboard\Application\DashboardPageView;
use DateTimeImmutable;
use LogicException;

final readonly class SuperAdminSyifaAiUsageOverviewPage
{
    public function __construct(private SyifaAiUsageOverviewProvider $usage) {}

    public function fromTrustedContext(mixed $context): DashboardPageView
    {
        if (! $context instanceof AuthorizationContext) {
            throw new LogicException('Super Admin dashboard context was not established.');
        }

        return new DashboardPageView('PlatformAdministration/SyifaAiUsage/SuperAdminSyifaAiUsageOverview', [
            'navigation' => [
                (new DashboardNavigationItem('dashboard', 'Dashboard', route('dashboard'), false))->toArray(),
                (new DashboardNavigationItem('registrations', 'Registrations', route('dashboard.registrations'), false))->toArray(),
                (new DashboardNavigationItem('tenants', 'Tenants', route('dashboard.tenants'), false))->toArray(),
                (new DashboardNavigationItem('onboarding-management', 'Onboarding', route('dashboard.onboarding-management'), false))->toArray(),
                (new DashboardNavigationItem('billing', 'Billing', route('dashboard.billing'), false))->toArray(),
                (new DashboardNavigationItem('commercial', 'Commercial', route('dashboard.commercial'), false))->toArray(),
                (new DashboardNavigationItem('payment-providers', 'Payment Providers', route('dashboard.payment-providers'), false))->toArray(),
                (new DashboardNavigationItem('syifa-ai-usage', 'SYIFA AI Usage', route('dashboard.syifa-ai-usage'), true))->toArray(),
                (new DashboardNavigationItem('audit', 'Audit Activity', route('dashboard.audit'), false))->toArray(),
            ],
            'breadcrumbs' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')],
                ['key' => 'syifa-ai-usage', 'label' => 'SYIFA AI Usage'],
            ],
            'pageTitle' => 'SYIFA AI usage',
            'pageDescription' => 'Monitor SYIFA AI token consumption and cost exposure across every tenant this month.',
            'identityName' => $context->name,
            'contextLabel' => 'Super Admin workspace',
            'syifaAiUsage' => $this->usage->provide((new DateTimeImmutable('today'))->format('Y-m-d'))->data,
        ]);
    }
}
