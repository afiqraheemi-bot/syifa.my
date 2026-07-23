<?php

declare(strict_types=1);

namespace App\Support\Authorization\Policies;

use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Authorization\Application\AuthorizationService;
use App\Support\Identity\ActorType;

/**
 * Shared policy for authenticated platform roles. Business-module policies
 * remain out of this foundation and may compose their own permission keys
 * through AuthorizationService in later work packages.
 */
final readonly class AuthenticatedPlatformUserPolicy
{
    private const CATEGORY = 'shared.platform-access';

    public function __construct(private AuthorizationService $authorization) {}

    public function access(): bool
    {
        return $this->hasRole('super_admin', 'website_designer');
    }

    public function support(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function design(): bool
    {
        return $this->hasRole('website_designer');
    }

    private function hasRole(string ...$allowedRoles): bool
    {
        $context = $this->authorization->resolve(self::CATEGORY);

        return $context instanceof AuthorizationContext
            && $context->actorType === ActorType::PlatformIdentity->value
            && in_array($context->role, $allowedRoles, true);
    }
}
