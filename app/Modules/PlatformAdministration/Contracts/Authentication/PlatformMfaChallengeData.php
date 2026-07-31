<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\Authentication;

final readonly class PlatformMfaChallengeData
{
    public function __construct(
        public string $state,
        public ?string $setupKey = null,
        public ?string $provisioningUri = null,
    ) {}
}
