<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Contracts\LaunchReadiness;

interface LaunchReadinessReadInterface
{
    public function forJob(string $onboardingJobId): ?LaunchReadinessData;

    public function forTenant(string $tenantId): ?LaunchReadinessData;

    /**
     * @param  list<string>  $onboardingJobIds
     * @return array<string, LaunchReadinessData>
     */
    public function forJobs(array $onboardingJobIds): array;
}
