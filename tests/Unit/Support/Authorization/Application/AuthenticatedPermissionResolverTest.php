<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Authorization\Application;

use App\Modules\PlatformAdministration\Contracts\Authorization\AuthorizationDecisionData;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformAuthorizationInterface;
use App\Support\Authorization\Application\AuthenticatedPermissionResolver;
use App\Support\Identity\ActorType;
use App\Support\Identity\AuthenticatedIdentity;
use App\Support\Identity\AuthenticatedIdentityInterface;
use App\Support\Identity\CurrentUserInterface;
use PHPUnit\Framework\TestCase;

final class AuthenticatedPermissionResolverTest extends TestCase
{
    public function test_platform_permission_is_resolved_from_the_authenticated_identity(): void
    {
        $authorization = new PermissionResolverPlatformAuthorization(true);
        $resolver = new AuthenticatedPermissionResolver(
            new PermissionResolverCurrentUser(new AuthenticatedIdentity(
                ActorType::PlatformIdentity,
                'platform-1',
                null,
                'super_admin',
                'Afiq',
            )),
            $authorization,
        );

        self::assertTrue($resolver->can('platform', 'platform.support'));
        self::assertSame(['platform-1', 'platform', 'platform.support'], $authorization->lastRequest);
    }

    public function test_unauthenticated_permission_resolution_fails_closed(): void
    {
        $authorization = new PermissionResolverPlatformAuthorization(true);

        self::assertFalse((new AuthenticatedPermissionResolver(
            new PermissionResolverCurrentUser(null),
            $authorization,
        ))->can('platform', 'platform.support'));
        self::assertNull($authorization->lastRequest);
    }

    public function test_clinic_owner_permission_resolution_fails_closed_until_a_policy_is_approved(): void
    {
        $authorization = new PermissionResolverPlatformAuthorization(true);
        $resolver = new AuthenticatedPermissionResolver(
            new PermissionResolverCurrentUser(new AuthenticatedIdentity(
                ActorType::ClinicOwner,
                'authority-1',
                'tenant-1',
                'clinic_owner',
                null,
            )),
            $authorization,
        );

        self::assertFalse($resolver->can('booking', 'booking.manage'));
        self::assertNull($authorization->lastRequest);
    }
}

final readonly class PermissionResolverCurrentUser implements CurrentUserInterface
{
    public function __construct(private ?AuthenticatedIdentityInterface $identity) {}

    public function resolve(): ?AuthenticatedIdentityInterface
    {
        return $this->identity;
    }
}

final class PermissionResolverPlatformAuthorization implements PlatformAuthorizationInterface
{
    /** @var array{string, string, string}|null */
    public ?array $lastRequest = null;

    public function __construct(private readonly bool $allowed) {}

    public function authorize(
        string $platformIdentityId,
        string $categoryKey,
        string $permissionKey,
        string $effectiveDateTime,
    ): AuthorizationDecisionData {
        $this->lastRequest = [$platformIdentityId, $categoryKey, $permissionKey];

        return new AuthorizationDecisionData(
            $platformIdentityId,
            $categoryKey,
            $permissionKey,
            $this->allowed,
            $this->allowed ? 'granted' : 'denied',
            $effectiveDateTime,
        );
    }
}
