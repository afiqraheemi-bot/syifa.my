<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Assignments;

use App\Modules\Onboarding\Contracts\Assignments\WebsiteDesignerAssignmentData;
use App\Modules\Onboarding\Contracts\Assignments\WebsiteDesignerAssignmentLookupInterface;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\PlatformIdentityId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\TenantId;

final readonly class GetActiveWebsiteDesignerAssignmentService
{
    public function __construct(private WebsiteDesignerAssignmentLookupInterface $assignments) {}

    public function execute(
        PlatformIdentityId $platformIdentityId,
        TenantId $tenantId,
    ): ?WebsiteDesignerAssignmentData {
        return $this->assignments->findActiveForTenant(
            $platformIdentityId->value,
            $tenantId->value,
        );
    }
}
