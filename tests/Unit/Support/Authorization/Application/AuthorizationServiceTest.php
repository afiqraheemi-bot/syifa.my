<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Authorization\Application;

use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Authorization\Application\AuthorizationService;
use App\Support\Identity\ActorType;
use App\Support\Identity\AuthenticatedIdentity;
use App\Support\Identity\AuthenticatedIdentityInterface;
use App\Support\Identity\CurrentUserInterface;
use App\Support\Identity\PermissionResolverInterface;
use App\Support\Identity\RoleResolverInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AuthorizationServiceTest extends TestCase
{
    public function test_it_returns_an_immutable_context_with_only_granted_permissions(): void
    {
        $identity = new AuthenticatedIdentity(
            ActorType::PlatformIdentity,
            'platform-1',
            null,
            'super_admin',
            'Afiq',
        );
        $service = new AuthorizationService(
            new AuthorizationServiceCurrentUser($identity),
            new AuthorizationServiceRoleResolver('super_admin'),
            new AuthorizationServicePermissionResolver(['platform.view']),
        );

        $context = $service->resolve('platform', ['platform.view', 'platform.manage', 'platform.view', '']);

        self::assertInstanceOf(AuthorizationContext::class, $context);
        self::assertTrue((new ReflectionClass($context))->isReadOnly());
        self::assertSame('platform-1', $context->identityId);
        self::assertSame('super_admin', $context->role);
        self::assertSame(['platform.view'], $context->permissions);
        self::assertTrue($context->can('platform.view'));
        self::assertFalse($context->can('platform.manage'));
    }

    public function test_it_returns_null_without_an_authenticated_identity(): void
    {
        $service = new AuthorizationService(
            new AuthorizationServiceCurrentUser(null),
            new AuthorizationServiceRoleResolver(null),
            new AuthorizationServicePermissionResolver([]),
        );

        self::assertNull($service->resolve('platform', ['platform.view']));
    }
}

final readonly class AuthorizationServiceCurrentUser implements CurrentUserInterface
{
    public function __construct(private ?AuthenticatedIdentityInterface $identity) {}

    public function resolve(): ?AuthenticatedIdentityInterface
    {
        return $this->identity;
    }
}

final readonly class AuthorizationServiceRoleResolver implements RoleResolverInterface
{
    public function __construct(private ?string $role) {}

    public function currentRole(): ?string
    {
        return $this->role;
    }
}

final readonly class AuthorizationServicePermissionResolver implements PermissionResolverInterface
{
    /** @param list<string> $granted */
    public function __construct(private array $granted) {}

    public function can(string $categoryKey, string $permissionKey): bool
    {
        return in_array($permissionKey, $this->granted, true);
    }
}
