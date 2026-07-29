<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Contracts\Tasks;

interface OnboardingTaskReadInterface
{
    /** @return array{jobId: string, jobVersion: int, tasks: list<array<string, mixed>>}|null */
    public function forTenant(string $tenantId): ?array;
}
