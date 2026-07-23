<?php

declare(strict_types=1);

namespace App\Support\Identity;

/**
 * The one shape every Controller across every actor type (Platform Identity,
 * Clinic Owner, and any future actor sharing this identity platform) is
 * allowed to depend on. Never a persistence model, never a Guard, never a
 * session array — just these five trusted facts about who is currently
 * authenticated.
 */
interface AuthenticatedIdentityInterface
{
    public function actorType(): string;

    public function identityId(): string;

    public function tenantId(): ?string;

    public function role(): string;

    public function name(): ?string;
}
