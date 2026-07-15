<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\Authorization;

final readonly class PlatformPermissionData
{
    public function __construct(
        public string $permissionKey,
        public string $categoryKey,
        public string $name,
        public string $description,
        public string $status,
    ) {}
}
