<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Domain\TenantContext\ValueObjects;

use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;
use App\Modules\TenantManagement\Domain\TenantContext\Exceptions\InvalidTenantContextValueException;

final readonly class TenantContextAssignment
{
    public function __construct(
        public string $assignmentId,
        public string $onboardingJobId,
        public PlatformIdentityId $platformIdentityId,
        public TenantId $tenantId,
    ) {
        if (! $this->isUuid($assignmentId) || ! $this->isUuid($onboardingJobId)) {
            throw new InvalidTenantContextValueException(
                'Assignment and Onboarding Job identifiers must be UUIDs.',
            );
        }
    }

    private function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1;
    }
}
