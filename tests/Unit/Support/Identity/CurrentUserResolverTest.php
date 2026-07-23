<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Identity;

use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipal;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipalResolverInterface;
use App\Modules\TenantManagement\Application\Session\GetCurrentClinicOwnerSessionService;
use App\Modules\TenantManagement\Contracts\Session\ClinicOwnerSessionState;
use App\Modules\TenantManagement\Contracts\Session\ClinicOwnerSessionStoreInterface;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextData;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextResolutionData;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextResolverInterface;
use App\Support\Identity\ActorType;
use App\Support\Identity\CurrentUserResolver;
use DateTimeImmutable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use PHPUnit\Framework\TestCase;

final class CurrentUserResolverTest extends TestCase
{
    public function test_returns_null_when_neither_guard_is_authenticated(): void
    {
        $resolver = $this->resolver(platformAuthenticated: false, clinicOwnerAuthenticated: false);

        self::assertNull($resolver->resolve());
        self::assertNull($resolver->currentTenantId());
    }

    public function test_resolves_a_platform_identity_with_no_tenant(): void
    {
        $resolver = $this->resolver(platformAuthenticated: true, clinicOwnerAuthenticated: false);

        $identity = $resolver->resolve();

        self::assertNotNull($identity);
        self::assertSame(ActorType::PlatformIdentity->value, $identity->actorType());
        self::assertSame('platform-1', $identity->identityId());
        self::assertNull($identity->tenantId());
        self::assertSame('super_admin', $identity->role());
        self::assertSame('Ada', $identity->name());
        self::assertNull($resolver->currentTenantId());
    }

    public function test_resolves_a_clinic_owner_scoped_to_their_tenant(): void
    {
        $resolver = $this->resolver(platformAuthenticated: false, clinicOwnerAuthenticated: true);

        $identity = $resolver->resolve();

        self::assertNotNull($identity);
        self::assertSame(ActorType::ClinicOwner->value, $identity->actorType());
        self::assertSame('authority-1', $identity->identityId());
        self::assertSame('tenant-1', $identity->tenantId());
        self::assertSame('clinic_owner', $identity->role());
        self::assertSame('tenant-1', $resolver->currentTenantId());
    }

    public function test_an_invalidated_clinic_owner_session_resolves_to_null(): void
    {
        $resolver = $this->resolver(platformAuthenticated: false, clinicOwnerAuthenticated: true, clinicOwnerSessionValid: false);

        self::assertNull($resolver->resolve());
    }

    public function test_resolution_is_cached_after_the_first_call(): void
    {
        $sessions = new CurrentUserResolverInMemoryClinicOwnerSessionStore($this->clinicOwnerState());
        $resolver = new CurrentUserResolver(
            new CurrentUserResolverFakeAuthFactory(false, true),
            new CurrentUserResolverFakePlatformPrincipalResolver(null),
            $sessions,
            new GetCurrentClinicOwnerSessionService($sessions, new CurrentUserResolverFakeTenantContextResolver(true), 30),
        );

        $resolver->resolve();
        $resolver->resolve();

        // `current()` is read once by `GetCurrentClinicOwnerSessionService`
        // (validation) and once directly by the resolver (full state) — both
        // only on the first `resolve()` call; the second call is cached.
        self::assertSame(2, $sessions->currentCallCount);
    }

    private function resolver(
        bool $platformAuthenticated,
        bool $clinicOwnerAuthenticated,
        bool $clinicOwnerSessionValid = true,
    ): CurrentUserResolver {
        $sessions = new CurrentUserResolverInMemoryClinicOwnerSessionStore($this->clinicOwnerState());

        return new CurrentUserResolver(
            new CurrentUserResolverFakeAuthFactory($platformAuthenticated, $clinicOwnerAuthenticated),
            new CurrentUserResolverFakePlatformPrincipalResolver(
                $platformAuthenticated ? new PlatformPrincipal('platform-1', 'super_admin', 'Ada') : null,
            ),
            $sessions,
            new GetCurrentClinicOwnerSessionService(
                $sessions,
                new CurrentUserResolverFakeTenantContextResolver($clinicOwnerSessionValid),
                30,
            ),
        );
    }

    private function clinicOwnerState(): ClinicOwnerSessionState
    {
        $now = new DateTimeImmutable;

        return new ClinicOwnerSessionState(
            tenantId: 'tenant-1',
            authorityId: 'authority-1',
            clinicOwnerIdentityId: 'identity-1',
            role: 'clinic_owner',
            authenticatedAt: $now,
            lastActivityAt: $now,
            absoluteExpiresAt: $now->modify('+12 hours'),
        );
    }
}

final class CurrentUserResolverFakeAuthFactory implements AuthFactory
{
    public function __construct(
        private readonly bool $platformAuthenticated,
        private readonly bool $clinicOwnerAuthenticated,
    ) {}

    public function guard($name = null): Guard
    {
        return new CurrentUserResolverFakeGuard(match ($name) {
            'platform_identity' => $this->platformAuthenticated,
            'clinic_owner' => $this->clinicOwnerAuthenticated,
            default => false,
        });
    }

    public function shouldUse($name): void {}
}

final class CurrentUserResolverFakeGuard implements Guard
{
    public function __construct(private readonly bool $authenticated) {}

    public function check(): bool
    {
        return $this->authenticated;
    }

    public function guest(): bool
    {
        return ! $this->authenticated;
    }

    public function user(): null
    {
        return null;
    }

    public function id(): null
    {
        return null;
    }

    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function hasUser(): bool
    {
        return $this->authenticated;
    }

    public function setUser($user): void {}
}

final readonly class CurrentUserResolverFakePlatformPrincipalResolver implements PlatformPrincipalResolverInterface
{
    public function __construct(private ?PlatformPrincipal $principal) {}

    public function resolve(DateTimeImmutable $resolvedAt): ?PlatformPrincipal
    {
        return $this->principal;
    }
}

final readonly class CurrentUserResolverFakeTenantContextResolver implements TenantContextResolverInterface
{
    public function __construct(private bool $valid) {}

    public function resolve(TenantContextResolutionData $resolution): ?TenantContextData
    {
        if (! $this->valid) {
            return null;
        }

        return new TenantContextData(
            platformIdentityId: null,
            tenantId: $resolution->tenantId,
            role: 'clinic_owner',
            assignment: null,
        );
    }
}

final class CurrentUserResolverInMemoryClinicOwnerSessionStore implements ClinicOwnerSessionStoreInterface
{
    public int $currentCallCount = 0;

    public function __construct(private readonly ?ClinicOwnerSessionState $state) {}

    public function establish(ClinicOwnerSessionState $state): void {}

    public function current(): ?ClinicOwnerSessionState
    {
        $this->currentCallCount++;

        return $this->state;
    }

    public function updateLastActivity(DateTimeImmutable $lastActivityAt): void {}

    public function invalidate(): void {}
}
