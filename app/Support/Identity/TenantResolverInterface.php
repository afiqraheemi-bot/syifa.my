<?php

declare(strict_types=1);

namespace App\Support\Identity;

/**
 * Resolves the current Tenant strictly from the already-authenticated
 * identity — never from a request parameter, cookie, header, hidden input,
 * or URL. Returns null for actors with no tenant-scoped session (a Platform
 * Identity acting outside any tenant context).
 */
interface TenantResolverInterface
{
    public function currentTenantId(): ?string;
}
