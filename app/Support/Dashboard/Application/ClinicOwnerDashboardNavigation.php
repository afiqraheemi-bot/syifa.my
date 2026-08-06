<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application;

final class ClinicOwnerDashboardNavigation
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
                ['key' => 'website', 'label' => 'Website', 'route' => 'dashboard.website'],
                ['key' => 'content', 'label' => 'Content', 'route' => 'dashboard.website.content'],
                ['key' => 'services', 'label' => 'Services', 'route' => 'dashboard.services'],
                ['key' => 'bookings', 'label' => 'Bookings', 'route' => 'dashboard.bookings'],
                ['key' => 'subscription', 'label' => 'Subscription', 'route' => 'dashboard.subscription'],
                ['key' => 'notifications', 'label' => 'Notifications', 'route' => 'dashboard.notifications'],
                ['key' => 'reports', 'label' => 'Reports', 'route' => 'dashboard.reports'],
            ],
        );
    }
}
