<?php

declare(strict_types=1);

namespace App\Support\Identity;

interface RoleResolverInterface
{
    public function currentRole(): ?string;
}
