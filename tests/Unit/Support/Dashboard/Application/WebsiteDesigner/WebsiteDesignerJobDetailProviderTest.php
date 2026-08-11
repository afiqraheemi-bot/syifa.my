<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Dashboard\Application\WebsiteDesigner;

use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerDashboardData;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerDashboardReadInterface;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerJobDetailData;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\WebsiteDesigner\Job\WebsiteDesignerJobDetailProvider;
use DateTimeImmutable;
use Tests\TestCase;

final class WebsiteDesignerJobDetailProviderTest extends TestCase
{
    public function test_it_projects_progress_stages_timeline_and_immutable_actions(): void
    {
        $provider = new WebsiteDesignerJobDetailProvider(new FixedJobDetailRead);
        $context = new AuthorizationContext(
            'platform_identity', 'designer-1', null, 'website_designer', 'Alya',
            'platform_identity', [],
        );

        $job = $provider->provide($context, 'job-1')?->data;

        self::assertNotNull($job);
        self::assertSame(80, $job['progress']['value']);
        self::assertSame('current', $job['stages'][2]['state']);
        self::assertSame(['Job Created', 'Assigned', 'In Review'], array_column($job['timeline'], 'title'));
        self::assertSame([true, true, true], array_column($job['actions'], 'available'));
        self::assertSame('#website-setup', $job['actions'][1]['href']);
        self::assertSame(
            route('dashboard.onboarding.custom-domain', ['jobId' => 'job-1']),
            $job['actions'][2]['href'],
        );
    }

    public function test_it_calculates_progress_from_completed_workflow_checkpoints(): void
    {
        $provider = new WebsiteDesignerJobDetailProvider(new FixedTaskJobDetailRead);
        $context = new AuthorizationContext(
            'platform_identity', 'designer-1', null, 'website_designer', 'Alya',
            'platform_identity', [],
        );

        $job = $provider->provide($context, 'job-1')?->data;

        self::assertNotNull($job);
        self::assertSame(33, $job['progress']['value']);
        self::assertSame('33% complete', $job['progress']['label']);
    }
}

final readonly class FixedTaskJobDetailRead implements WebsiteDesignerDashboardReadInterface
{
    public function forPlatformIdentity(string $platformIdentityId): WebsiteDesignerDashboardData
    {
        return new WebsiteDesignerDashboardData(0, 0, 0, 0, 0, 0, []);
    }

    public function queue(string $platformIdentityId, ?string $status, ?string $cursor, int $limit, ?string $search): array
    {
        return [];
    }

    public function detail(string $platformIdentityId, string $onboardingJobId): ?WebsiteDesignerJobDetailData
    {
        return new WebsiteDesignerJobDetailData(
            'assignment-1',
            $onboardingJobId,
            'tenant-1',
            'website-1',
            'assigned',
            6,
            new DateTimeImmutable('2026-08-20T09:00:00Z'),
            new DateTimeImmutable('2026-08-22T09:00:00Z'),
            [],
            [
                ['key' => 'clinic_inputs', 'status' => 'completed', 'responsibility' => 'clinic_owner'],
                ['key' => 'service_setup', 'status' => 'completed', 'responsibility' => 'website_designer'],
                ['key' => 'website_setup', 'status' => 'not_ready', 'responsibility' => 'website_designer'],
                ['key' => 'booking_setup', 'status' => 'not_ready', 'responsibility' => 'website_designer'],
                ['key' => 'website_approval', 'status' => 'not_ready', 'responsibility' => 'clinic_owner'],
                ['key' => 'launch_readiness', 'status' => 'not_ready', 'responsibility' => 'website_designer'],
            ],
        );
    }
}

final readonly class FixedJobDetailRead implements WebsiteDesignerDashboardReadInterface
{
    public function forPlatformIdentity(string $platformIdentityId): WebsiteDesignerDashboardData
    {
        return new WebsiteDesignerDashboardData(0, 0, 0, 0, 0, 0, []);
    }

    public function queue(string $platformIdentityId, ?string $status, ?string $cursor, int $limit, ?string $search): array
    {
        return [];
    }

    public function detail(string $platformIdentityId, string $onboardingJobId): ?WebsiteDesignerJobDetailData
    {
        return new WebsiteDesignerJobDetailData(
            'assignment-1',
            $onboardingJobId,
            'tenant-1',
            'website-1',
            'in_review',
            1,
            new DateTimeImmutable('2026-08-20T09:00:00Z'),
            new DateTimeImmutable('2026-08-22T09:00:00Z'),
            [
                'job_created_at' => new DateTimeImmutable('2026-08-19T09:00:00Z'),
                'assigned_at' => new DateTimeImmutable('2026-08-20T09:00:00Z'),
                'in_review_at' => new DateTimeImmutable('2026-08-22T09:00:00Z'),
            ],
        );
    }
}
