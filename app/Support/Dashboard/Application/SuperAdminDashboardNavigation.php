<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application;

final class SuperAdminDashboardNavigation
{
    /** @return list<array{kind: string, key: string, label: string, href: string, icon: null, current: bool}> */
    public static function items(?string $routeName): array
    {
        $currentKey = self::currentKey($routeName);

        return array_values(array_map(
            static fn (array $item): array => (new DashboardNavigationItem(
                $item[0],
                $item[1],
                route($item[2]),
                $item[0] === $currentKey,
            ))->toArray(),
            [
                ['dashboard', 'Dashboard', 'dashboard'],
                ['registrations', 'Registrations', 'dashboard.registrations'],
                ['tenants', 'Tenants', 'dashboard.tenants'],
                ['onboarding-management', 'Onboarding', 'dashboard.onboarding-management'],
                ['billing', 'Billing', 'dashboard.billing'],
                ['commercial', 'Commercial', 'dashboard.commercial'],
                ['payment-providers', 'Payment Providers', 'dashboard.payment-providers'],
                ['syifa-ai-usage', 'SYIFA AI Usage', 'dashboard.syifa-ai-usage'],
                ['notifications', 'Notifications', 'dashboard.notifications'],
                ['audit', 'Audit Activity', 'dashboard.audit'],
                ['reports', 'Reports', 'dashboard.reports'],
            ],
        ));
    }

    private static function currentKey(?string $routeName): string
    {
        if ($routeName === null || $routeName === 'dashboard') {
            return 'dashboard';
        }

        foreach ([
            'registrations',
            'tenants',
            'onboarding-management',
            'billing',
            'commercial',
            'payment-providers',
            'syifa-ai-usage',
            'notifications',
            'audit',
            'reports',
        ] as $key) {
            if (str_starts_with($routeName, 'dashboard.'.$key)) {
                return $key;
            }
        }

        return 'dashboard';
    }
}
