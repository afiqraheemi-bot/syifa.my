<?php

declare(strict_types=1);

namespace App\Support\Identity;

/** Resolves one named permission from the trusted authenticated identity only. */
interface PermissionResolverInterface
{
    public function can(string $categoryKey, string $permissionKey): bool;
}
