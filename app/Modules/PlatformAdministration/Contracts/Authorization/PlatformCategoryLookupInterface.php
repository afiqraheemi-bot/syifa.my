<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\Authorization;

interface PlatformCategoryLookupInterface
{
    public function findCategory(string $categoryKey): ?PlatformCategoryData;
}
