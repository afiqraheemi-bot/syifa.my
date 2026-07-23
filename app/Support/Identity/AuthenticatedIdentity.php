<?php

declare(strict_types=1);

namespace App\Support\Identity;

final readonly class AuthenticatedIdentity implements AuthenticatedIdentityInterface
{
    public function __construct(
        private ActorType $actorType,
        private string $identityId,
        private ?string $tenantId,
        private string $role,
        private ?string $name,
    ) {}

    public function actorType(): string
    {
        return $this->actorType->value;
    }

    public function identityId(): string
    {
        return $this->identityId;
    }

    public function tenantId(): ?string
    {
        return $this->tenantId;
    }

    public function role(): string
    {
        return $this->role;
    }

    public function name(): ?string
    {
        return $this->name;
    }
}
