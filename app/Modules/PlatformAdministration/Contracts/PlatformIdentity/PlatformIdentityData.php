<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\PlatformIdentity;

final readonly class PlatformIdentityData
{
    public function __construct(
        public string $id,
        public string $email,
        public string $name,
        public string $role,
        public string $status,
    ) {}
}
