<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Contracts\Administration;

interface PendingOnboardingJobsReadInterface
{
    public function countPending(): int;

    /** @return list<array{id: string, clinic_name: string, status: string, updated_at: string}> */
    public function recentPending(int $limit): array;
}
