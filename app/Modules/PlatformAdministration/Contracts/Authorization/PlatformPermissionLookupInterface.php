<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\Authorization;

interface PlatformPermissionLookupInterface
{
    public function findPermission(string $permissionKey): ?PlatformPermissionData;
}
