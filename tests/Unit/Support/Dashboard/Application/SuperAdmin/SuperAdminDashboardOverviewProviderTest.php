<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Dashboard\Application\SuperAdmin;

use App\Modules\PlatformAdministration\Contracts\Dashboard\PlatformDashboardActivityData;
use App\Modules\PlatformAdministration\Contracts\Dashboard\PlatformDashboardData;
use App\Modules\PlatformAdministration\Contracts\Dashboard\PlatformDashboardReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\SuperAdmin\PlatformQuickActionsProvider;
use App\Support\Dashboard\Application\SuperAdmin\PlatformRecentActivityProvider;
use App\Support\Dashboard\Application\SuperAdmin\PlatformSummaryProvider;
use App\Support\Dashboard\Application\SuperAdmin\SuperAdminDashboardOverviewProvider;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SuperAdminDashboardOverviewProviderTest extends TestCase
{
    public function test_it_composes_platform_summaries_actions_and_activity_from_immutable_query_data(): void
    {
        $read = new FixedPlatformDashboardRead;
        $provider = new SuperAdminDashboardOverviewProvider(
            new PlatformSummaryProvider($read),
            new PlatformQuickActionsProvider('/dashboard/tenants', '/dashboard/billing', '/dashboard/commercial'),
            new PlatformRecentActivityProvider($read),
        );
        $context = new AuthorizationContext(
            'platform_identity', 'admin-1', null, 'super_admin', 'Sarah',
            'platform_identity', [],
        );

        $overview = $provider->for($context);

        self::assertSame('Welcome back, Sarah', $overview['welcomeTitle']);
        self::assertSame(['12', '9', '3', '5', '8', '42', 'Operational'], array_column($overview['summaries'], 'value'));
        self::assertSame([true, true, true], array_column($overview['quickActions'], 'available'));
        self::assertSame(
            ['/dashboard/tenants', '/dashboard/billing', '/dashboard/commercial'],
            array_column($overview['quickActions'], 'href'),
        );
        self::assertSame('Tenant Activated', $overview['recentActivity'][0]['title']);
        self::assertSame('Outcome: Success', $overview['recentActivity'][0]['description']);
    }
}

final readonly class FixedPlatformDashboardRead implements PlatformDashboardReadInterface
{
    public function overview(): PlatformDashboardData
    {
        return new PlatformDashboardData(
            12, 9, 3, 5, 8, 42, true,
            [new PlatformDashboardActivityData(
                'audit-1',
                'tenant.activated',
                'success',
                new DateTimeImmutable('2026-08-25T10:00:00+08:00'),
            )],
        );
    }
}
