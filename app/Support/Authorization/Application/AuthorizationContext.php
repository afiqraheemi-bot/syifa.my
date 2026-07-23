<?php

declare(strict_types=1);

namespace App\Support\Authorization\Application;

final readonly class AuthorizationContext
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(
        public string $actorType,
        public string $identityId,
        public ?string $tenantId,
        public string $role,
        public ?string $name,
        public string $categoryKey,
        public array $permissions,
    ) {}

    public function can(string $permissionKey): bool
    {
        return in_array($permissionKey, $this->permissions, true);
    }
}
