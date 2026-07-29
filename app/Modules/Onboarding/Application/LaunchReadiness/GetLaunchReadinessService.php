<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\LaunchReadiness;

use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerDashboardReadInterface;
use App\Modules\Onboarding\Contracts\LaunchReadiness\LaunchReadinessAccessContext;
use App\Modules\Onboarding\Contracts\LaunchReadiness\LaunchReadinessData;
use App\Modules\Onboarding\Contracts\LaunchReadiness\LaunchReadinessReadInterface;

final readonly class GetLaunchReadinessService
{
    public function __construct(
        private LaunchReadinessReadInterface $readiness,
        private WebsiteDesignerDashboardReadInterface $designerJobs,
    ) {}

    public function execute(
        LaunchReadinessAccessContext $access,
        string $onboardingJobId,
    ): ?LaunchReadinessData {
        if ($access->role === 'super_admin') {
            return $this->readiness->forJob($onboardingJobId);
        }
        if ($access->role === 'website_designer') {
            return $this->designerJobs->detail($access->actorId, $onboardingJobId) === null
                ? null
                : $this->readiness->forJob($onboardingJobId);
        }
        if ($access->role === 'clinic_owner' && $access->tenantId !== null) {
            $assessment = $this->readiness->forTenant($access->tenantId);

            return $assessment?->onboardingJobId === $onboardingJobId ? $assessment : null;
        }

        return null;
    }
}
