<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\PlatformAdministration\Application\Authentication;

use App\Modules\PlatformAdministration\Application\Authentication\PlatformPrincipalResolver;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipal;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformSessionState;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformSessionStoreInterface;
use App\Modules\PlatformAdministration\Contracts\PlatformIdentity\PlatformIdentityData;
use App\Modules\PlatformAdministration\Contracts\PlatformIdentity\PlatformIdentityLookupInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PlatformPrincipalResolverTest extends TestCase
{
    public function test_resolve_returns_a_platform_principal_and_updates_session_activity(): void
    {
        $store = new InMemoryResolvablePlatformSessionStore(
            new PlatformSessionState(
                new PlatformPrincipal(self::IDENTITY_ID, 'super_admin', 'Super Admin'),
                new DateTimeImmutable('2026-07-19T10:00:00Z'),
                new DateTimeImmutable('2026-07-19T10:05:00Z'),
                new DateTimeImmutable('2026-07-19T11:05:00Z'),
                new DateTimeImmutable('2026-07-20T10:00:00Z'),
            ),
        );
        $resolver = new PlatformPrincipalResolver(
            $store,
            new FixedPlatformIdentityLookupForResolver(new PlatformIdentityData(
                self::IDENTITY_ID,
                'admin@example.test',
                'Super Admin',
                'super_admin',
                'active',
            )),
        );

        $principal = $resolver->resolve(new DateTimeImmutable('2026-07-19T10:15:00Z'));

        self::assertInstanceOf(PlatformPrincipal::class, $principal);
        self::assertSame(self::IDENTITY_ID, $principal->platformIdentityId);
        self::assertSame(1, $store->updateCount);
    }

    public function test_resolve_invalidates_expired_or_inactive_sessions(): void
    {
        $store = new InMemoryResolvablePlatformSessionStore(
            new PlatformSessionState(
                new PlatformPrincipal(self::IDENTITY_ID, 'website_designer', 'Website Designer'),
                new DateTimeImmutable('2026-07-19T10:00:00Z'),
                new DateTimeImmutable('2026-07-19T10:05:00Z'),
                new DateTimeImmutable('2026-07-19T10:10:00Z'),
                new DateTimeImmutable('2026-07-19T10:20:00Z'),
            ),
        );
        $resolver = new PlatformPrincipalResolver(
            $store,
            new FixedPlatformIdentityLookupForResolver(new PlatformIdentityData(
                self::IDENTITY_ID,
                'designer@example.test',
                'Website Designer',
                'website_designer',
                'suspended',
            )),
        );

        self::assertNull($resolver->resolve(new DateTimeImmutable('2026-07-19T10:15:00Z')));
        self::assertSame(1, $store->invalidateCount);
    }

    private const IDENTITY_ID = '00000000-0000-4000-8000-000000000222';
}

final class InMemoryResolvablePlatformSessionStore implements PlatformSessionStoreInterface
{
    public int $updateCount = 0;

    public int $invalidateCount = 0;

    public function __construct(private ?PlatformSessionState $state) {}

    public function establish(PlatformPrincipal $principal, DateTimeImmutable $authenticatedAt, bool $remember = false): PlatformSessionState
    {
        return $this->state ?? throw new \LogicException('Not expected in this test.');
    }

    public function current(DateTimeImmutable $at): ?PlatformSessionState
    {
        return $this->state;
    }

    public function updateLastActivity(DateTimeImmutable $lastActivityAt): void
    {
        $this->updateCount++;
    }

    public function invalidate(): void
    {
        $this->invalidateCount++;
        $this->state = null;
    }
}

final class FixedPlatformIdentityLookupForResolver implements PlatformIdentityLookupInterface
{
    public function __construct(private PlatformIdentityData $identity) {}

    public function findById(string $platformIdentityId): ?PlatformIdentityData
    {
        return $this->identity->id === $platformIdentityId ? $this->identity : null;
    }
}
