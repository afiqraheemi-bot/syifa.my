<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Contracts\Administration;

interface PendingOnboardingJobsReadInterface
{
    public function countPending(): int;
}
