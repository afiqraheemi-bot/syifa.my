<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Dashboard\Application;

use App\Support\Dashboard\Application\ClinicOwnerDashboardNavigation;
use Tests\TestCase;

final class ClinicOwnerDashboardNavigationTest extends TestCase
{
    public function test_items_are_labelled_in_english_by_default(): void
    {
        $items = ClinicOwnerDashboardNavigation::items('dashboard');

        self::assertSame(
            ['Dashboard', 'Website', 'Content', 'Services', 'Bookings', 'Blog', 'Subscription', 'Notifications', 'Reports', 'Account & Security'],
            array_column($items, 'label'),
        );
    }

    public function test_items_follow_the_owners_chosen_language(): void
    {
        $originalLocale = app()->getLocale();
        app()->setLocale('ms');

        try {
            $items = ClinicOwnerDashboardNavigation::items('dashboard');

            self::assertSame(
                ['Dashboard', 'Website', 'Kandungan', 'Servis', 'Tempahan', 'Blog', 'Langganan', 'Notifikasi', 'Laporan', 'Akaun & Keselamatan'],
                array_column($items, 'label'),
            );
        } finally {
            app()->setLocale($originalLocale);
        }
    }

    public function test_the_current_item_is_marked_regardless_of_language(): void
    {
        $items = ClinicOwnerDashboardNavigation::items('bookings');

        $current = array_values(array_filter($items, static fn (array $item): bool => $item['current']));
        self::assertCount(1, $current);
        self::assertSame('bookings', $current[0]['key']);
    }
}
