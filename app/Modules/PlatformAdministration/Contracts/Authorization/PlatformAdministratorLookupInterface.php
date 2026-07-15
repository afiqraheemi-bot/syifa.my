<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\Authorization;

interface PlatformAdministratorLookupInterface
{
    public function findAdministrator(string $administratorId): ?PlatformAdministratorData;
}
