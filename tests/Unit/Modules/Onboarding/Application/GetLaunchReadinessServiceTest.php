<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Onboarding\Application;

use App\Modules\Onboarding\Application\LaunchReadiness\GetLaunchReadinessService;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerDashboardData;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerDashboardReadInterface;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerJobDetailData;
use App\Modules\Onboarding\Contracts\LaunchReadiness\LaunchReadinessAccessContext;
use App\Modules\Onboarding\Contracts\LaunchReadiness\LaunchReadinessData;
use App\Modules\Onboarding\Contracts\LaunchReadiness\LaunchReadinessReadInterface;
use PHPUnit\Framework\TestCase;

final class GetLaunchReadinessServiceTest extends TestCase
{
    public function test_super_admin_can_read_and_unassigned_designer_fails_closed(): void
    {
        $service = new GetLaunchReadinessService(
            new FixedLaunchReadinessRead,
            new EmptyDesignerDashboardRead,
        );

        self::assertNotNull($service->execute(
            new LaunchReadinessAccessContext('admin', 'super_admin', null),
            FixedLaunchReadinessRead::JOB_ID,
        ));
        self::assertNull($service->execute(
            new LaunchReadinessAccessContext('designer', 'website_designer', null),
            FixedLaunchReadinessRead::JOB_ID,
        ));
    }

    public function test_clinic_owner_can_read_only_matching_tenant_job(): void
    {
        $service = new GetLaunchReadinessService(
            new FixedLaunchReadinessRead,
            new EmptyDesignerDashboardRead,
        );

        self::assertNotNull($service->execute(
            new LaunchReadinessAccessContext('owner', 'clinic_owner', FixedLaunchReadinessRead::TENANT_ID),
            FixedLaunchReadinessRead::JOB_ID,
        ));
        self::assertNull($service->execute(
            new LaunchReadinessAccessContext('owner', 'clinic_owner', 'foreign-tenant'),
            FixedLaunchReadinessRead::JOB_ID,
        ));
    }
}

final readonly class FixedLaunchReadinessRead implements LaunchReadinessReadInterface
{
    public const string JOB_ID = '00000000-0000-4000-8000-000000000001';

    public const string TENANT_ID = '00000000-0000-4000-8000-000000000002';

    public function forJob(string $onboardingJobId): ?LaunchReadinessData
    {
        return $onboardingJobId === self::JOB_ID ? $this->data() : null;
    }

    public function forTenant(string $tenantId): ?LaunchReadinessData
    {
        return $tenantId === self::TENANT_ID ? $this->data() : null;
    }

    public function forJobs(array $onboardingJobIds): array
    {
        return in_array(self::JOB_ID, $onboardingJobIds, true)
            ? [self::JOB_ID => $this->data()]
            : [];
    }

    private function data(): LaunchReadinessData
    {
        return new LaunchReadinessData(
            self::JOB_ID,
            self::TENANT_ID,
            '00000000-0000-4000-8000-000000000003',
            'blocked',
            [['key' => 'approval', 'label' => 'Approval', 'satisfied' => false, 'detail' => 'Required.']],
        );
    }
}

final readonly class EmptyDesignerDashboardRead implements WebsiteDesignerDashboardReadInterface
{
    public function forPlatformIdentity(string $platformIdentityId): WebsiteDesignerDashboardData
    {
        return new WebsiteDesignerDashboardData(0, 0, 0, 0, 0, 0, []);
    }

    public function queue(
        string $platformIdentityId,
        ?string $status,
        ?string $cursor,
        int $limit,
        ?string $search,
    ): array {
        return [];
    }

    public function detail(
        string $platformIdentityId,
        string $onboardingJobId,
    ): ?WebsiteDesignerJobDetailData {
        return null;
    }
}
