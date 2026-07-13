<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\PlatformIdentity;

interface PlatformIdentityLookupInterface
{
    public function findById(string $platformIdentityId): ?PlatformIdentityData;
}
