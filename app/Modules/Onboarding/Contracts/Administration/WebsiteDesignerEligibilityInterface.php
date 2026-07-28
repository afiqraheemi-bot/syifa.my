<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Contracts\Administration;

interface WebsiteDesignerEligibilityInterface
{
    public function isEligible(string $platformIdentityId): bool;
}
