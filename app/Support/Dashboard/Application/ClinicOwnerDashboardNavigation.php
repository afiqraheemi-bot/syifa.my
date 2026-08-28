<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application;

final class ClinicOwnerDashboardNavigation
{
    /** @return list<array{kind: string, key: string, label: string, href: string, icon: null, current: bool}> */
    public static function items(string $current): array
    {
        $isMalay = app()->getLocale() === 'ms';

        return array_values(array_map(
            static fn (array $item): array => (new DashboardNavigationItem(
                $item['key'],
                $isMalay ? $item['labelMs'] : $item['labelEn'],
                route($item['route']),
                $item['key'] === $current,
            ))->toArray(),
            [
                ['key' => 'dashboard', 'labelEn' => 'Dashboard', 'labelMs' => 'Dashboard', 'route' => 'dashboard'],
                ['key' => 'website', 'labelEn' => 'Website', 'labelMs' => 'Website', 'route' => 'dashboard.website'],
                ['key' => 'content', 'labelEn' => 'Content', 'labelMs' => 'Kandungan', 'route' => 'dashboard.website.content'],
                ['key' => 'services', 'labelEn' => 'Services', 'labelMs' => 'Servis', 'route' => 'dashboard.services'],
                ['key' => 'bookings', 'labelEn' => 'Bookings', 'labelMs' => 'Tempahan', 'route' => 'dashboard.bookings'],
                ['key' => 'blog', 'labelEn' => 'Blog', 'labelMs' => 'Blog', 'route' => 'dashboard.blog'],
                ['key' => 'subscription', 'labelEn' => 'Subscription', 'labelMs' => 'Langganan', 'route' => 'dashboard.subscription'],
                ['key' => 'notifications', 'labelEn' => 'Notifications', 'labelMs' => 'Notifikasi', 'route' => 'dashboard.notifications'],
                ['key' => 'reports', 'labelEn' => 'Reports', 'labelMs' => 'Laporan', 'route' => 'dashboard.reports'],
                ['key' => 'account', 'labelEn' => 'Account & Security', 'labelMs' => 'Akaun & Keselamatan', 'route' => 'dashboard.account'],
            ],
        ));
    }
}
