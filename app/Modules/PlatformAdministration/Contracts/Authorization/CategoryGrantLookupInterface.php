<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\Authorization;

interface CategoryGrantLookupInterface
{
    public function findGrant(string $administratorId, string $categoryKey): ?CategoryGrantData;
}
