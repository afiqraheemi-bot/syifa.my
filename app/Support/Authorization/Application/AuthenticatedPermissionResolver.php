<?php

declare(strict_types=1);

namespace App\Support\Authorization\Application;

use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformAuthorizationInterface;
use App\Support\Identity\ActorType;
use App\Support\Identity\CurrentUserInterface;
use App\Support\Identity\PermissionResolverInterface;
use DateTimeImmutable;
use DateTimeZone;

final readonly class AuthenticatedPermissionResolver implements PermissionResolverInterface
{
    public function __construct(
        private CurrentUserInterface $currentUser,
        private PlatformAuthorizationInterface $platformAuthorization,
    ) {}

    public function can(string $categoryKey, string $permissionKey): bool
    {
        $identity = $this->currentUser->resolve();
        if ($identity === null || $identity->actorType() !== ActorType::PlatformIdentity->value) {
            return false;
        }

        return $this->platformAuthorization->authorize(
            $identity->identityId(),
            $categoryKey,
            $permissionKey,
            (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
        )->allowed;
    }
}
