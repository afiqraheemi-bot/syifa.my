<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Dashboard\Application\WebsiteDesigner;

use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerDashboardData;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerDashboardReadInterface;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerJobDetailData;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerRecentAssignmentData;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\WebsiteDesigner\WebsiteDesignerAssignmentsProvider;
use App\Support\Dashboard\Application\WebsiteDesigner\WebsiteDesignerDashboardOverviewProvider;
use App\Support\Dashboard\Application\WebsiteDesigner\WebsiteDesignerQuickActionsProvider;
use App\Support\Dashboard\Application\WebsiteDesigner\WebsiteDesignerRecentAssignmentsProvider;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class WebsiteDesignerDashboardOverviewProviderTest extends TestCase
{
    public function test_it_composes_immutable_designer_dashboard_projections_from_the_query_contract(): void
    {
        $read = new WebsiteDesignerFixedDashboardRead;
        $context = new AuthorizationContext(
            'platform_identity',
            'designer-1',
            null,
            'website_designer',
            'Alya',
            'platform_identity',
            [],
        );
        $provider = new WebsiteDesignerDashboardOverviewProvider(
            new WebsiteDesignerAssignmentsProvider($read),
            new WebsiteDesignerQuickActionsProvider,
            new WebsiteDesignerRecentAssignmentsProvider($read),
        );

        $overview = $provider->for($context);

        self::assertSame('Welcome back, Alya', $overview['welcomeTitle']);
        self::assertSame(
            ['3', '1', '1', '1', '0', '4'],
            array_column($overview['summaries'], 'value'),
        );
        self::assertSame('pending-content', $overview['summaries'][1]['key']);
        self::assertSame('Review & revision', $overview['recentAssignments'][0]['description']);
        self::assertSame('2026-08-24T09:30:00+08:00', $overview['recentAssignments'][0]['occurredAt']);
        self::assertCount(3, $overview['quickActions']);
        self::assertSame([false, false, false], array_column($overview['quickActions'], 'available'));
        self::assertSame(['designer-1', 'designer-1'], $read->identityIds);
    }

    public function test_it_uses_the_role_name_when_the_identity_has_no_display_name(): void
    {
        $read = new WebsiteDesignerFixedDashboardRead;
        $provider = new WebsiteDesignerDashboardOverviewProvider(
            new WebsiteDesignerAssignmentsProvider($read),
            new WebsiteDesignerQuickActionsProvider,
            new WebsiteDesignerRecentAssignmentsProvider($read),
        );

        $overview = $provider->for(new AuthorizationContext(
            'platform_identity',
            'designer-1',
            null,
            'website_designer',
            null,
            'platform_identity',
            [],
        ));

        self::assertSame('Welcome back, Website Designer', $overview['welcomeTitle']);
    }
}

final class WebsiteDesignerFixedDashboardRead implements WebsiteDesignerDashboardReadInterface
{
    /** @var list<string> */
    public array $identityIds = [];

    public function forPlatformIdentity(string $platformIdentityId): WebsiteDesignerDashboardData
    {
        $this->identityIds[] = $platformIdentityId;

        return new WebsiteDesignerDashboardData(
            assignedJobs: 3,
            pendingContentCollection: 1,
            websiteSetup: 1,
            reviewAndRevision: 1,
            readyToPublish: 0,
            completedProjects: 4,
            recentAssignments: [
                new WebsiteDesignerRecentAssignmentData(
                    'assignment-1',
                    'job-1',
                    'tenant-1',
                    'correction_required',
                    new DateTimeImmutable('2026-08-24 09:30:00+08:00'),
                ),
            ],
        );
    }

    public function queue(string $platformIdentityId, ?string $status, ?string $cursor, int $limit, ?string $search): array
    {
        return [];
    }

    public function detail(string $platformIdentityId, string $onboardingJobId): ?WebsiteDesignerJobDetailData
    {
        return null;
    }
}
