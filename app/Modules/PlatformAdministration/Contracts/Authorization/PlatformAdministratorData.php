<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\Authorization;

final readonly class PlatformAdministratorData
{
    public function __construct(
        public string $administratorId,
        public string $platformIdentityId,
        public string $status,
    ) {}
}
