<?php

declare(strict_types=1);

namespace Tests\Contract\Modules\Onboarding\Assignments;

use App\Modules\Onboarding\Application\Assignments\GetActiveWebsiteDesignerAssignmentService;
use App\Modules\Onboarding\Application\Assignments\GetOnboardingJobAssignmentService;
use App\Modules\Onboarding\Contracts\Assignments\WebsiteDesignerAssignmentData;
use App\Modules\Onboarding\Contracts\Assignments\WebsiteDesignerAssignmentLookupInterface;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingJobId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\PlatformIdentityId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\TenantId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class WebsiteDesignerAssignmentContractsTest extends TestCase
{
    public function test_active_assignment_lookup_requires_platform_identity_and_tenant_scope(): void
    {
        $lookup = $this->lookup();
        $service = new GetActiveWebsiteDesignerAssignmentService($lookup);
        $assignment = $service->execute(
            new PlatformIdentityId('00000000-0000-4000-8000-000000000021'),
            new TenantId('00000000-0000-4000-8000-000000000022'),
        );

        self::assertNotNull($assignment);
        self::assertSame('00000000-0000-4000-8000-000000000021', $assignment->platformIdentityId);
        self::assertSame('00000000-0000-4000-8000-000000000022', $assignment->tenantId);
    }

    public function test_onboarding_job_lookup_returns_only_its_active_assignment(): void
    {
        $assignment = new GetOnboardingJobAssignmentService($this->lookup())->execute(
            new OnboardingJobId('00000000-0000-4000-8000-000000000023'),
        );

        self::assertNotNull($assignment);
        self::assertSame('00000000-0000-4000-8000-000000000023', $assignment->onboardingJobId);
    }

    public function test_assignment_contract_data_is_immutable(): void
    {
        self::assertTrue((new ReflectionClass(WebsiteDesignerAssignmentData::class))->isReadOnly());
    }

    private function lookup(): WebsiteDesignerAssignmentLookupInterface
    {
        return new class implements WebsiteDesignerAssignmentLookupInterface
        {
            public function findActiveForTenant(
                string $platformIdentityId,
                string $tenantId,
            ): ?WebsiteDesignerAssignmentData {
                return $this->data(
                    onboardingJobId: '00000000-0000-4000-8000-000000000023',
                    platformIdentityId: $platformIdentityId,
                    tenantId: $tenantId,
                );
            }

            public function findActiveForOnboardingJob(
                string $onboardingJobId,
            ): ?WebsiteDesignerAssignmentData {
                return $this->data(
                    onboardingJobId: $onboardingJobId,
                    platformIdentityId: '00000000-0000-4000-8000-000000000021',
                    tenantId: '00000000-0000-4000-8000-000000000022',
                );
            }

            private function data(
                string $onboardingJobId,
                string $platformIdentityId,
                string $tenantId,
            ): WebsiteDesignerAssignmentData {
                return new WebsiteDesignerAssignmentData(
                    assignmentId: '00000000-0000-4000-8000-000000000024',
                    onboardingJobId: $onboardingJobId,
                    platformIdentityId: $platformIdentityId,
                    tenantId: $tenantId,
                    assignedAt: new DateTimeImmutable('2026-07-13T10:00:00+08:00'),
                );
            }
        };
    }
}
