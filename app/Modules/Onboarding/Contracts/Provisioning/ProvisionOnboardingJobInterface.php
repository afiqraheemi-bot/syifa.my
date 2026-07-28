<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Contracts\Provisioning;

use DateTimeImmutable;

interface ProvisionOnboardingJobInterface
{
    public function execute(
        string $onboardingJobId,
        string $tenantId,
        string $websiteId,
        DateTimeImmutable $occurredAt,
    ): string;
}
