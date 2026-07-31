<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\PlatformAdministration\Application\Authentication;

use App\Modules\PlatformAdministration\Application\Authentication\PlatformMfaChallengeService;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditCorrelationIdResolverInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\Authentication\PendingPlatformAuthenticationData;
use App\Modules\PlatformAdministration\Contracts\Authentication\PendingPlatformAuthenticationStoreInterface;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformMfaEnrollmentData;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformMfaEnrollmentRepositoryInterface;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipal;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformSessionState;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformSessionStoreInterface;
use App\Modules\PlatformAdministration\Domain\AuditEntry\AuditEntry;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditEntryId;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use DateTimeImmutable;
use Illuminate\Encryption\Encrypter;
use OTPHP\InternalClock;
use OTPHP\TOTP;
use PHPUnit\Framework\TestCase;

final class PlatformMfaChallengeServiceTest extends TestCase
{
    public function test_first_challenge_enrolls_totp_and_only_then_establishes_the_privileged_session(): void
    {
        $harness = new MfaHarness;
        $at = new DateTimeImmutable('2026-09-09T10:00:00Z');
        $principal = $this->principal();

        $challenge = $harness->service->begin($principal, 'designer@example.test', true, $at);

        self::assertSame('mfa_enrollment_required', $challenge->state);
        self::assertNotNull($challenge->setupKey);
        self::assertStringStartsWith('otpauth://totp/', (string) $challenge->provisioningUri);
        self::assertSame(0, $harness->sessions->establishCount);

        $code = TOTP::createFromSecret($challenge->setupKey, new InternalClock)->at($at->getTimestamp());
        $authenticated = $harness->service->complete($code, $at);

        self::assertSame($principal, $authenticated);
        self::assertSame(1, $harness->sessions->establishCount);
        self::assertTrue($harness->sessions->lastRemember);
        self::assertNotNull($harness->enrollments->enrollment);
        self::assertNotSame($challenge->setupKey, $harness->enrollments->enrollment?->encryptedTotpSecret);
        self::assertSame([
            'platform.authentication.mfa_challenge_issued',
            'platform.authentication.mfa_enrolled',
            'platform.authentication.mfa_challenge',
        ], array_column($harness->audit->entries, 'action'));
    }

    public function test_existing_enrollment_requires_a_fresh_code_and_replay_fails_closed(): void
    {
        $harness = new MfaHarness;
        $at = new DateTimeImmutable('2026-09-09T10:00:00Z');
        $secret = TOTP::generate(new InternalClock, 20)->getSecret();
        $encrypted = $harness->encrypter->encryptString($secret);
        $step = intdiv($at->getTimestamp(), 30);
        $harness->enrollments->enrollment = new PlatformMfaEnrollmentData(
            self::IDENTITY_ID,
            $encrypted,
            $at->modify('-1 day'),
            $step,
            1,
        );

        self::assertSame('mfa_required', $harness->service
            ->begin($this->principal(), 'designer@example.test', false, $at)->state);

        $code = TOTP::createFromSecret($secret, new InternalClock)->at($at->getTimestamp());
        self::assertNull($harness->service->complete($code, $at));
        self::assertSame(0, $harness->sessions->establishCount);
        self::assertSame('invalid_or_replayed_code', $harness->audit->entries[1]->outcome->reasonCode);
    }

    public function test_expired_pending_challenge_is_cleared_and_cannot_create_a_session(): void
    {
        $harness = new MfaHarness;
        $at = new DateTimeImmutable('2026-09-09T10:00:00Z');
        $challenge = $harness->service->begin($this->principal(), 'designer@example.test', false, $at);
        $code = TOTP::createFromSecret((string) $challenge->setupKey, new InternalClock)
            ->at($at->modify('+6 minutes')->getTimestamp());

        self::assertNull($harness->service->complete($code, $at->modify('+6 minutes')));
        self::assertNull($harness->pending->current());
        self::assertSame(0, $harness->sessions->establishCount);
    }

    private function principal(): PlatformPrincipal
    {
        return new PlatformPrincipal(self::IDENTITY_ID, 'website_designer', 'Website Designer');
    }

    private const string IDENTITY_ID = '00000000-0000-4000-8000-000000000555';
}

final class MfaHarness
{
    public readonly Encrypter $encrypter;

    public readonly MfaInMemoryEnrollmentRepository $enrollments;

    public readonly MfaInMemoryPendingStore $pending;

    public readonly MfaInMemorySessionStore $sessions;

    public readonly MfaAuditRecorder $audit;

    public readonly PlatformMfaChallengeService $service;

    public function __construct()
    {
        $this->encrypter = new Encrypter(random_bytes(32), 'AES-256-CBC');
        $this->enrollments = new MfaInMemoryEnrollmentRepository;
        $this->pending = new MfaInMemoryPendingStore;
        $this->sessions = new MfaInMemorySessionStore;
        $this->audit = new MfaAuditRecorder;
        $this->service = new PlatformMfaChallengeService(
            $this->enrollments,
            $this->pending,
            $this->sessions,
            $this->audit,
            new MfaCorrelationResolver,
            $this->encrypter,
        );
    }
}

final class MfaInMemoryEnrollmentRepository implements PlatformMfaEnrollmentRepositoryInterface
{
    public ?PlatformMfaEnrollmentData $enrollment = null;

    public function find(string $platformIdentityId): ?PlatformMfaEnrollmentData
    {
        return $this->enrollment?->platformIdentityId === $platformIdentityId
            ? $this->enrollment
            : null;
    }

    public function enroll(
        string $platformIdentityId,
        string $encryptedTotpSecret,
        int $verifiedTimeStep,
        DateTimeImmutable $confirmedAt,
    ): PlatformMfaEnrollmentData {
        return $this->enrollment = new PlatformMfaEnrollmentData(
            $platformIdentityId,
            $encryptedTotpSecret,
            $confirmedAt,
            $verifiedTimeStep,
            1,
        );
    }

    public function recordVerification(
        string $platformIdentityId,
        int $expectedVersion,
        int $verifiedTimeStep,
        DateTimeImmutable $verifiedAt,
    ): bool {
        if ($this->enrollment === null
            || $this->enrollment->platformIdentityId !== $platformIdentityId
            || $this->enrollment->version !== $expectedVersion
            || ($this->enrollment->lastVerifiedTimeStep ?? -1) >= $verifiedTimeStep) {
            return false;
        }
        $this->enrollment = new PlatformMfaEnrollmentData(
            $platformIdentityId,
            $this->enrollment->encryptedTotpSecret,
            $this->enrollment->confirmedAt,
            $verifiedTimeStep,
            $expectedVersion + 1,
        );

        return true;
    }
}

final class MfaInMemoryPendingStore implements PendingPlatformAuthenticationStoreInterface
{
    public ?PendingPlatformAuthenticationData $pending = null;

    public function establish(PendingPlatformAuthenticationData $pending): void
    {
        $this->pending = $pending;
    }

    public function current(): ?PendingPlatformAuthenticationData
    {
        return $this->pending;
    }

    public function clear(): void
    {
        $this->pending = null;
    }
}

final class MfaInMemorySessionStore implements PlatformSessionStoreInterface
{
    public int $establishCount = 0;

    public bool $lastRemember = false;

    public function establish(
        PlatformPrincipal $principal,
        DateTimeImmutable $authenticatedAt,
        bool $remember = false,
    ): PlatformSessionState {
        $this->establishCount++;
        $this->lastRemember = $remember;

        return new PlatformSessionState(
            $principal,
            $authenticatedAt,
            $authenticatedAt,
            $authenticatedAt->modify('+15 minutes'),
            $authenticatedAt->modify('+8 hours'),
        );
    }

    public function current(DateTimeImmutable $at): ?PlatformSessionState
    {
        return null;
    }

    public function updateLastActivity(DateTimeImmutable $lastActivityAt): void {}

    public function invalidate(): void {}
}

final class MfaAuditRecorder implements AuditEntryRecorderInterface
{
    /** @var list<AuditEntryData> */
    public array $entries = [];

    public function record(AuditEntryData $auditEntry): AuditEntry
    {
        $this->entries[] = $auditEntry;

        return AuditEntry::record(
            new AuditEntryId($auditEntry->auditEntryId),
            $auditEntry->occurredAt,
            AuditActorType::from($auditEntry->actor->type),
            $auditEntry->actor->identityId,
            $auditEntry->tenantId,
            $auditEntry->action,
            $auditEntry->target->type,
            $auditEntry->target->id,
            AuditOutcomeType::from($auditEntry->outcome->outcome),
            $auditEntry->outcome->reasonCode,
            $auditEntry->correlationId,
            $auditEntry->safeMetadata,
        );
    }
}

final readonly class MfaCorrelationResolver implements AuditCorrelationIdResolverInterface
{
    public function resolve(): string
    {
        return '00000000-0000-4000-8000-000000000999';
    }
}
