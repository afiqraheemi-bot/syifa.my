<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Repositories;

use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\OnboardingJob;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingJobId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\TenantId;

interface OnboardingJobRepositoryInterface
{
    public function find(TenantId $tenantId, OnboardingJobId $onboardingJobId): ?OnboardingJob;

    public function findById(OnboardingJobId $onboardingJobId): ?OnboardingJob;

    public function save(OnboardingJob $onboardingJob): void;
}
