<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Contracts\LaunchReadiness;

final readonly class LaunchReadinessAccessContext
{
    public function __construct(
        public string $actorId,
        public string $role,
        public ?string $tenantId,
    ) {}
}
