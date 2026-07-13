<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Application\TenantContext;

use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextData;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextResolutionData;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextResolverInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;
use App\Modules\TenantManagement\Domain\TenantContext\TenantContext;
use App\Modules\TenantManagement\Domain\TenantContext\ValueObjects\PlatformIdentityId;
use App\Modules\TenantManagement\Domain\TenantContext\ValueObjects\TenantContextAssignment;
use App\Modules\TenantManagement\Domain\TenantContext\ValueObjects\TenantContextRole;

final readonly class ResolveTenantContextService
{
    public function __construct(private TenantContextResolverInterface $resolver) {}

    public function execute(TenantContextResolutionData $resolution): ?TenantContext
    {
        $context = $this->resolver->resolve($resolution);

        return $context === null ? null : $this->toDomain($context);
    }

    private function toDomain(TenantContextData $context): TenantContext
    {
        $platformIdentityId = $context->platformIdentityId === null
            ? null
            : new PlatformIdentityId($context->platformIdentityId);
        $assignment = $context->assignment === null
            ? null
            : new TenantContextAssignment(
                assignmentId: $context->assignment->assignmentId,
                onboardingJobId: $context->assignment->onboardingJobId,
                platformIdentityId: new PlatformIdentityId($context->assignment->platformIdentityId),
                tenantId: new TenantId($context->assignment->tenantId),
            );

        return new TenantContext(
            platformIdentityId: $platformIdentityId,
            tenantId: new TenantId($context->tenantId),
            role: TenantContextRole::from($context->role),
            assignment: $assignment,
        );
    }
}
