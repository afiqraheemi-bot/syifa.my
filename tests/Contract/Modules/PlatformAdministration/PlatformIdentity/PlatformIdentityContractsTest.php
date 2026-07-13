<?php

declare(strict_types=1);

namespace Tests\Contract\Modules\PlatformAdministration\PlatformIdentity;

use App\Modules\Onboarding\Contracts\Assignments\WebsiteDesignerAssignmentData;
use App\Modules\Onboarding\Contracts\Assignments\WebsiteDesignerAssignmentLookupInterface;
use App\Modules\PlatformAdministration\Application\PlatformIdentity\GetPlatformIdentityService;
use App\Modules\PlatformAdministration\Application\PlatformIdentity\GetWebsiteDesignerAssignmentService;
use App\Modules\PlatformAdministration\Contracts\PlatformIdentity\PlatformIdentityData;
use App\Modules\PlatformAdministration\Contracts\PlatformIdentity\PlatformIdentityLookupInterface;
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\PlatformIdentityRole;
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\ValueObjects\PlatformIdentityId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class PlatformIdentityContractsTest extends TestCase
{
    public function test_identity_lookup_contract_supports_a_substitutable_read_adapter(): void
    {
        $lookup = new class implements PlatformIdentityLookupInterface
        {
            public function findById(string $platformIdentityId): ?PlatformIdentityData
            {
                return new PlatformIdentityData(
                    id: $platformIdentityId,
                    email: 'designer@example.test',
                    name: 'Website Designer',
                    role: 'website_designer',
                    status: 'active',
                );
            }
        };

        $identity = new GetPlatformIdentityService($lookup)->execute(
            new PlatformIdentityId('00000000-0000-4000-8000-000000000001'),
        );

        self::assertNotNull($identity);
        self::assertSame(PlatformIdentityRole::WebsiteDesigner, $identity->role);
        self::assertTrue($identity->isActive());
    }

    public function test_assignment_contract_exposes_only_assignment_reference_data(): void
    {
        $assignedAt = new DateTimeImmutable('2026-07-13T10:00:00+08:00');
        $lookup = new class($assignedAt) implements WebsiteDesignerAssignmentLookupInterface
        {
            public function __construct(private readonly DateTimeImmutable $assignedAt) {}

            public function findActiveForTenant(
                string $platformIdentityId,
                string $tenantId,
            ): ?WebsiteDesignerAssignmentData {
                return new WebsiteDesignerAssignmentData(
                    assignmentId: '00000000-0000-4000-8000-000000000004',
                    onboardingJobId: '00000000-0000-4000-8000-000000000002',
                    platformIdentityId: $platformIdentityId,
                    tenantId: $tenantId,
                    assignedAt: $this->assignedAt,
                );
            }

            public function findActiveForOnboardingJob(
                string $onboardingJobId,
            ): ?WebsiteDesignerAssignmentData {
                return null;
            }
        };

        $assignment = new GetWebsiteDesignerAssignmentService($lookup)->execute(
            new PlatformIdentityId('00000000-0000-4000-8000-000000000001'),
            '00000000-0000-4000-8000-000000000003',
        );

        self::assertNotNull($assignment);
        self::assertSame('00000000-0000-4000-8000-000000000001', $assignment->platformIdentityId);
        self::assertSame('00000000-0000-4000-8000-000000000003', $assignment->tenantId);
    }

    public function test_contract_data_objects_are_readonly(): void
    {
        self::assertTrue((new ReflectionClass(PlatformIdentityData::class))->isReadOnly());
        self::assertTrue((new ReflectionClass(WebsiteDesignerAssignmentData::class))->isReadOnly());
    }
}
