<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\Authorization;

interface PlatformAdministratorLookupInterface
{
    /**
     * Resolves the at-most-one Platform Administrator governance profile for a given,
     * already-authenticated Platform Identity. Never accepts a bare governance-profile ID
     * from untrusted transport — Platform Identity is the only trusted caller identity.
     */
    public function findByPlatformIdentityId(string $platformIdentityId): ?PlatformAdministratorData;
}
