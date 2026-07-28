<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Contracts\Administration;

interface SuperAdminOnboardingReadInterface
{
    /** @return array{jobs: list<array<string, mixed>>, designers: list<array<string, mixed>>} */
    public function overview(?string $status, ?string $search): array;
}
