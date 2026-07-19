<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\Authentication;

final readonly class PlatformPrincipal
{
    public function __construct(
        public string $platformIdentityId,
        public string $role,
        public string $name,
    ) {}
}
