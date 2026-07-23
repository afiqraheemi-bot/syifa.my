<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Authorization\Policies;

use App\Support\Authorization\Application\AuthorizationService;
use App\Support\Authorization\Policies\AuthenticatedPlatformUserPolicy;
use App\Support\Identity\ActorType;
use App\Support\Identity\AuthenticatedIdentity;
use App\Support\Identity\AuthenticatedIdentityInterface;
use App\Support\Identity\CurrentUserInterface;
use App\Support\Identity\PermissionResolverInterface;
use App\Support\Identity\RoleResolverInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AuthenticatedPlatformUserPolicyTest extends TestCase
{
    #[DataProvider('roleDecisions')]
    public function test_platform_policy_enforces_current_platform_roles(
        ?ActorType $actorType,
        ?string $role,
        bool $access,
        bool $support,
        bool $design,
    ): void {
        $policy = new AuthenticatedPlatformUserPolicy($this->authorization($actorType, $role));

        self::assertSame($access, $policy->access());
        self::assertSame($support, $policy->support());
        self::assertSame($design, $policy->design());
    }

    /**
     * @return iterable<string, array{?ActorType, ?string, bool, bool, bool}>
     */
    public static function roleDecisions(): iterable
    {
        yield 'super admin' => [ActorType::PlatformIdentity, 'super_admin', true, true, false];
        yield 'website designer' => [ActorType::PlatformIdentity, 'website_designer', true, false, true];
        yield 'clinic owner cannot cross into platform policy' => [ActorType::ClinicOwner, 'clinic_owner', false, false, false];
        yield 'unauthenticated' => [null, null, false, false, false];
    }

    private function authorization(?ActorType $actorType, ?string $role): AuthorizationService
    {
        $identity = $actorType === null || $role === null
            ? null
            : new AuthenticatedIdentity($actorType, 'identity-1', null, $role, 'Test User');

        return new AuthorizationService(
            new class($identity) implements CurrentUserInterface
            {
                public function __construct(private readonly ?AuthenticatedIdentityInterface $identity) {}

                public function resolve(): ?AuthenticatedIdentityInterface
                {
                    return $this->identity;
                }
            },
            new class($role) implements RoleResolverInterface
            {
                public function __construct(private readonly ?string $role) {}

                public function currentRole(): ?string
                {
                    return $this->role;
                }
            },
            new class implements PermissionResolverInterface
            {
                public function can(string $categoryKey, string $permissionKey): bool
                {
                    return false;
                }
            },
        );
    }
}
