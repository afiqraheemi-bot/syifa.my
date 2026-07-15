<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\Authorization;

/**
 * Category Grant read-model data.
 *
 * `grantedAt` uses RFC 3339 UTC in the canonical `YYYY-MM-DDTHH:MM:SSZ` format.
 */
final readonly class CategoryGrantData
{
    /**
     * @param  list<string>  $permissionKeys
     */
    public function __construct(
        public string $administratorId,
        public string $categoryKey,
        public array $permissionKeys,
        public string $status,
        public string $grantedAt,
    ) {}
}
