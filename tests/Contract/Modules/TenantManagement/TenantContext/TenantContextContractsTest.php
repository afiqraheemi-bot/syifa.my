<?php

declare(strict_types=1);

namespace Tests\Contract\Modules\TenantManagement\TenantContext;

use App\Modules\TenantManagement\Application\TenantContext\ResolveTenantContextService;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextAssignmentData;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextData;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextResolutionData;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextResolverInterface;
use App\Modules\TenantManagement\Domain\TenantContext\ValueObjects\TenantContextRole;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class TenantContextContractsTest extends TestCase
{
    public function test_resolver_contract_produces_an_immutable_website_designer_context(): void
    {
        $resolution = $this->resolution();
        $context = new ResolveTenantContextService($this->resolver())->execute($resolution);

        self::assertNotNull($context);
        self::assertSame(TenantContextRole::WebsiteDesigner, $context->role);
        self::assertSame($resolution->tenantId, $context->tenantId->value);
        self::assertNotNull($context->assignment);
        self::assertSame($resolution->assignment?->assignmentId, $context->assignment->assignmentId);
    }

    public function test_resolver_contract_fails_closed_when_the_context_cannot_be_resolved(): void
    {
        $resolver = new class implements TenantContextResolverInterface
        {
            public function resolve(TenantContextResolutionData $resolution): ?TenantContextData
            {
                return null;
            }
        };

        self::assertNull(new ResolveTenantContextService($resolver)->execute($this->resolution()));
    }

    public function test_tenant_context_contract_data_is_immutable(): void
    {
        self::assertTrue((new ReflectionClass(TenantContextData::class))->isReadOnly());
        self::assertTrue((new ReflectionClass(TenantContextResolutionData::class))->isReadOnly());
        self::assertTrue((new ReflectionClass(TenantContextAssignmentData::class))->isReadOnly());
    }

    private function resolver(): TenantContextResolverInterface
    {
        return new class implements TenantContextResolverInterface
        {
            public function resolve(TenantContextResolutionData $resolution): ?TenantContextData
            {
                return new TenantContextData(
                    platformIdentityId: $resolution->platformIdentityId,
                    tenantId: $resolution->tenantId,
                    role: $resolution->role,
                    assignment: $resolution->assignment,
                );
            }
        };
    }

    private function resolution(): TenantContextResolutionData
    {
        $platformIdentityId = '00000000-0000-4000-8000-000000000031';
        $tenantId = '00000000-0000-4000-8000-000000000032';

        return new TenantContextResolutionData(
            platformIdentityId: $platformIdentityId,
            tenantId: $tenantId,
            role: 'website_designer',
            assignment: new TenantContextAssignmentData(
                assignmentId: '00000000-0000-4000-8000-000000000033',
                onboardingJobId: '00000000-0000-4000-8000-000000000034',
                platformIdentityId: $platformIdentityId,
                tenantId: $tenantId,
            ),
        );
    }
}
