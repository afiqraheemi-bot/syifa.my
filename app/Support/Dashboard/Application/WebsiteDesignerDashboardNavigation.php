<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application;

final class WebsiteDesignerDashboardNavigation
{
    /** @return list<array{kind: string, key: string, label: string, href: string, icon: null, current: bool}> */
    public static function items(string $current): array
    {
        return array_map(
            static fn (array $item): array => (new DashboardNavigationItem(
                $item['key'],
                $item['label'],
                route($item['route']),
                $item['key'] === $current,
            ))->toArray(),
            [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'dashboard'],
                ['key' => 'onboarding', 'label' => 'Onboarding', 'route' => 'dashboard.onboarding'],
                ['key' => 'reports', 'label' => 'Reports', 'route' => 'dashboard.reports'],
            ],
        );
    }
}
