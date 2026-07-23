<?php

declare(strict_types=1);

namespace App\Support\Authorization\Application;

use App\Support\Identity\CurrentUserInterface;
use App\Support\Identity\RoleResolverInterface;

final readonly class AuthenticatedRoleResolver implements RoleResolverInterface
{
    public function __construct(private CurrentUserInterface $currentUser) {}

    public function currentRole(): ?string
    {
        return $this->currentUser->resolve()?->role();
    }
}
