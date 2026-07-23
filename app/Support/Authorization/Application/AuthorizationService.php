<?php

declare(strict_types=1);

namespace App\Support\Authorization\Application;

use App\Support\Identity\CurrentUserInterface;
use App\Support\Identity\PermissionResolverInterface;
use App\Support\Identity\RoleResolverInterface;

final readonly class AuthorizationService
{
    public function __construct(
        private CurrentUserInterface $currentUser,
        private RoleResolverInterface $roles,
        private PermissionResolverInterface $permissions,
    ) {}

    /**
     * @param  list<string>  $permissionKeys
     */
    public function resolve(string $categoryKey, array $permissionKeys = []): ?AuthorizationContext
    {
        $identity = $this->currentUser->resolve();
        $role = $this->roles->currentRole();
        if ($identity === null || $role === null) {
            return null;
        }

        $granted = [];
        foreach (array_values(array_unique($permissionKeys)) as $permissionKey) {
            if ($permissionKey !== '' && $this->permissions->can($categoryKey, $permissionKey)) {
                $granted[] = $permissionKey;
            }
        }

        return new AuthorizationContext(
            $identity->actorType(),
            $identity->identityId(),
            $identity->tenantId(),
            $role,
            $identity->name(),
            $categoryKey,
            $granted,
        );
    }
}
