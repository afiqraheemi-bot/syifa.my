<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\PlatformAdministration\Application\Authentication;

use App\Modules\PlatformAdministration\Application\Authentication\AuthenticatePlatformSessionService;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipal;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformSessionState;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformSessionStoreInterface;
use App\Modules\PlatformAdministration\Contracts\PlatformIdentity\PlatformIdentityData;
use App\Modules\PlatformAdministration\Contracts\PlatformIdentity\PlatformIdentityLookupInterface;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\CredentialVerificationInterface;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\CredentialVerificationResult;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AuthenticatePlatformSessionServiceTest extends TestCase
{
    public function test_successful_authentication_returns_a_platform_principal_and_establishes_a_session(): void
    {
        $store = new InMemoryPlatformSessionStore;
        $service = new AuthenticatePlatformSessionService(
            new FixedCredentialVerification(true, self::IDENTITY_ID),
            new FixedPlatformIdentityLookup(new PlatformIdentityData(
                self::IDENTITY_ID,
                'designer@example.test',
                'Website Designer',
                'website_designer',
                'active',
            )),
            $store,
        );

        $principal = $service->authenticate(
            'designer@example.test',
            'correct horse battery staple',
            new DateTimeImmutable('2026-07-19T10:00:00Z'),
        );

        self::assertInstanceOf(PlatformPrincipal::class, $principal);
        self::assertSame(self::IDENTITY_ID, $principal->platformIdentityId);
        self::assertSame('website_designer', $principal->role);
        self::assertSame('Website Designer', $principal->name);
        self::assertSame(1, $store->establishCount);
    }

    public function test_invalid_password_rejected_without_establishing_a_session(): void
    {
        $store = new InMemoryPlatformSessionStore;
        $service = new AuthenticatePlatformSessionService(
            new FixedCredentialVerification(false, null),
            new FixedPlatformIdentityLookup(new PlatformIdentityData(
                self::IDENTITY_ID,
                'designer@example.test',
                'Website Designer',
                'website_designer',
                'active',
            )),
            $store,
        );

        self::assertNull($service->authenticate(
            'designer@example.test',
            'wrong',
            new DateTimeImmutable('2026-07-19T10:00:00Z'),
        ));
        self::assertSame(0, $store->establishCount);
    }

    public function test_inactive_or_locked_platform_identity_is_rejected_without_establishing_a_session(): void
    {
        $store = new InMemoryPlatformSessionStore;
        $service = new AuthenticatePlatformSessionService(
            new FixedCredentialVerification(true, self::IDENTITY_ID),
            new FixedPlatformIdentityLookup(new PlatformIdentityData(
                self::IDENTITY_ID,
                'designer@example.test',
                'Website Designer',
                'website_designer',
                'suspended',
            )),
            $store,
        );

        self::assertNull($service->authenticate(
            'designer@example.test',
            'correct horse battery staple',
            new DateTimeImmutable('2026-07-19T10:00:00Z'),
        ));
        self::assertSame(0, $store->establishCount);
    }

    private const IDENTITY_ID = '00000000-0000-4000-8000-000000000111';
}

final class FixedCredentialVerification implements CredentialVerificationInterface
{
    public function __construct(
        private bool $verified,
        private ?string $platformIdentityId,
    ) {}

    public function verify(
        string $email,
        #[\SensitiveParameter] string $plainPassword,
        DateTimeImmutable $verifiedAt,
    ): CredentialVerificationResult {
        return new CredentialVerificationResult($this->verified, $this->platformIdentityId);
    }
}

final class FixedPlatformIdentityLookup implements PlatformIdentityLookupInterface
{
    public function __construct(private PlatformIdentityData $identity) {}

    public function findById(string $platformIdentityId): ?PlatformIdentityData
    {
        return $this->identity->id === $platformIdentityId ? $this->identity : null;
    }
}

final class InMemoryPlatformSessionStore implements PlatformSessionStoreInterface
{
    public int $establishCount = 0;

    public function establish(
        PlatformPrincipal $principal,
        DateTimeImmutable $authenticatedAt,
    ): PlatformSessionState {
        $this->establishCount++;

        return new PlatformSessionState(
            $principal,
            $authenticatedAt,
            $authenticatedAt,
            $authenticatedAt,
            $authenticatedAt,
        );
    }

    public function current(DateTimeImmutable $at): ?PlatformSessionState
    {
        return null;
    }

    public function updateLastActivity(DateTimeImmutable $lastActivityAt): void
    {
        //
    }

    public function invalidate(): void
    {
        //
    }
}
