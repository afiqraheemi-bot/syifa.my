<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Authorization\Application;

use App\Support\Authorization\Application\AuthenticatedRoleResolver;
use App\Support\Identity\ActorType;
use App\Support\Identity\AuthenticatedIdentity;
use App\Support\Identity\AuthenticatedIdentityInterface;
use App\Support\Identity\CurrentUserInterface;
use PHPUnit\Framework\TestCase;

final class AuthenticatedRoleResolverTest extends TestCase
{
    public function test_it_resolves_the_role_only_from_the_authenticated_identity(): void
    {
        $resolver = new AuthenticatedRoleResolver(new RoleResolverCurrentUser(
            new AuthenticatedIdentity(ActorType::PlatformIdentity, 'platform-1', null, 'website_designer', 'Amina'),
        ));

        self::assertSame('website_designer', $resolver->currentRole());
    }

    public function test_it_returns_null_without_an_authenticated_identity(): void
    {
        self::assertNull((new AuthenticatedRoleResolver(new RoleResolverCurrentUser(null)))->currentRole());
    }
}

final readonly class RoleResolverCurrentUser implements CurrentUserInterface
{
    public function __construct(private ?AuthenticatedIdentityInterface $identity) {}

    public function resolve(): ?AuthenticatedIdentityInterface
    {
        return $this->identity;
    }
}
