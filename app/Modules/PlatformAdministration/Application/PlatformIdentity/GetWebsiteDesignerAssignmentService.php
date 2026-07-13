<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Application\PlatformIdentity;

use App\Modules\Onboarding\Contracts\Assignments\WebsiteDesignerAssignmentData;
use App\Modules\Onboarding\Contracts\Assignments\WebsiteDesignerAssignmentLookupInterface;
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\ValueObjects\PlatformIdentityId;

final readonly class GetWebsiteDesignerAssignmentService
{
    public function __construct(private WebsiteDesignerAssignmentLookupInterface $assignments) {}

    public function execute(
        PlatformIdentityId $platformIdentityId,
        string $tenantId,
    ): ?WebsiteDesignerAssignmentData {
        return $this->assignments->findActiveForTenant($platformIdentityId->value, $tenantId);
    }
}
