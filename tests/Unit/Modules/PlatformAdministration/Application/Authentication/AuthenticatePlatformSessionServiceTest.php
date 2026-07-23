<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\PlatformAdministration\Application\Authentication;

use App\Modules\PlatformAdministration\Application\Authentication\AuthenticatePlatformSessionService;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditCorrelationIdResolverInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipal;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformSessionState;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformSessionStoreInterface;
use App\Modules\PlatformAdministration\Contracts\PlatformIdentity\PlatformIdentityData;
use App\Modules\PlatformAdministration\Contracts\PlatformIdentity\PlatformIdentityLookupInterface;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\PlatformWorkforceCredentialData;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\PlatformWorkforceCredentialLookupInterface;
use App\Modules\PlatformAdministration\Domain\AuditEntry\AuditEntry;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditEntryId;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use DateTimeImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\StatefulGuard;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;

final class AuthenticatePlatformSessionServiceTest extends TestCase
{
    public function test_successful_authentication_records_a_success_audit_and_establishes_a_session(): void
    {
        $harness = $this->harness(
            attemptSucceeds: true,
            credential: $this->credential(emailVerified: true),
            identity: $this->identity(),
        );

        $principal = $harness->service->authenticate(
            'designer@example.test',
            'correct horse battery staple',
            new DateTimeImmutable('2026-07-19T10:00:00Z'),
        );

        self::assertInstanceOf(PlatformPrincipal::class, $principal);
        self::assertSame(self::IDENTITY_ID, $principal->platformIdentityId);
        self::assertSame(1, $harness->store->establishCount);
        self::assertSame(0, $harness->guard->logoutCount);
        self::assertCount(1, $harness->auditRecorder->entries);

        $entry = $harness->auditRecorder->entries[0];
        self::assertSame('platform.authentication.login', $entry->action);
        self::assertSame(AuditActorType::PlatformIdentity->value, $entry->actor->type);
        self::assertSame(self::IDENTITY_ID, $entry->actor->identityId);
        self::assertSame('platform_session', $entry->target->type);
        self::assertSame(self::IDENTITY_ID, $entry->target->id);
        self::assertSame(AuditOutcomeType::Succeeded->value, $entry->outcome->outcome);
        self::assertNull($entry->outcome->reasonCode);
        self::assertSame(self::CORRELATION_ID, $entry->correlationId);
        self::assertSame(['actor_role' => 'website_designer'], $entry->safeMetadata);
    }

    public function test_successful_authentication_stops_when_audit_recording_fails(): void
    {
        $harness = $this->harness(
            attemptSucceeds: true,
            credential: $this->credential(emailVerified: true),
            identity: $this->identity(),
            throwOnAuditRecord: true,
        );

        self::assertNull($harness->service->authenticate(
            'designer@example.test',
            'correct horse battery staple',
            new DateTimeImmutable('2026-07-19T10:00:00Z'),
        ));
        self::assertSame(0, $harness->store->establishCount);
        self::assertSame(1, $harness->guard->logoutCount);
        self::assertSame(0, $harness->logger->recordsCount());
    }

    public function test_invalid_password_records_a_failure_audit(): void
    {
        $harness = $this->harness(
            attemptSucceeds: false,
            credential: $this->credential(),
            identity: $this->identity(),
        );

        self::assertNull($harness->service->authenticate(
            'designer@example.test',
            'wrong',
            new DateTimeImmutable('2026-07-19T10:00:00Z'),
        ));
        self::assertSame(1, $harness->auditRecorder->entriesCount());
        self::assertSame(0, $harness->guard->logoutCount);

        $entry = $harness->auditRecorder->entries[0];
        self::assertSame(AuditOutcomeType::Failed->value, $entry->outcome->outcome);
        self::assertSame('invalid_credentials', $entry->outcome->reasonCode);
        self::assertSame(AuditActorType::PlatformIdentity->value, $entry->actor->type);
        self::assertSame(self::IDENTITY_ID, $entry->actor->identityId);
    }

    public function test_invalid_password_audit_failure_falls_back_to_emergency_logging(): void
    {
        $harness = $this->harness(
            attemptSucceeds: false,
            credential: $this->credential(),
            identity: $this->identity(),
            throwOnAuditRecord: true,
        );

        self::assertNull($harness->service->authenticate(
            'designer@example.test',
            'wrong',
            new DateTimeImmutable('2026-07-19T10:00:00Z'),
        ));
        self::assertSame(0, $harness->store->establishCount);
        self::assertCount(1, $harness->logger->criticalRecords);
        self::assertSame('platform.security.audit.emergency', $harness->logger->criticalRecords[0]['message']);
        self::assertSame('platform.authentication.login', $harness->logger->criticalRecords[0]['context']['action']);
        self::assertSame('failed', $harness->logger->criticalRecords[0]['context']['outcome']);
        self::assertSame('invalid_credentials', $harness->logger->criticalRecords[0]['context']['reason_code']);
        self::assertSame(self::CORRELATION_ID, $harness->logger->criticalRecords[0]['context']['correlation_id']);
    }

    public function test_locked_account_records_locked_reason(): void
    {
        $harness = $this->harness(
            attemptSucceeds: false,
            credential: $this->credential(lockoutUntil: new DateTimeImmutable('2026-07-19T10:15:00Z')),
            identity: $this->identity(),
        );

        self::assertNull($harness->service->authenticate(
            'designer@example.test',
            'wrong',
            new DateTimeImmutable('2026-07-19T10:00:00Z'),
        ));
        self::assertSame('account_locked', $harness->auditRecorder->entries[0]->outcome->reasonCode);
    }

    public function test_inactive_account_records_inactive_reason(): void
    {
        $harness = $this->harness(
            attemptSucceeds: false,
            credential: $this->credential(accountStatus: 'suspended'),
            identity: $this->identity(),
        );

        self::assertNull($harness->service->authenticate(
            'designer@example.test',
            'wrong',
            new DateTimeImmutable('2026-07-19T10:00:00Z'),
        ));
        self::assertSame('account_inactive', $harness->auditRecorder->entries[0]->outcome->reasonCode);
    }

    public function test_unverified_email_fails_closed_without_session_creation(): void
    {
        $harness = $this->harness(
            attemptSucceeds: true,
            credential: $this->credential(emailVerified: false),
            identity: $this->identity(),
        );

        self::assertNull($harness->service->authenticate(
            'designer@example.test',
            'correct horse battery staple',
            new DateTimeImmutable('2026-07-19T10:00:00Z'),
        ));
        self::assertSame(0, $harness->store->establishCount);
        self::assertSame(1, $harness->guard->logoutCount);
        self::assertSame('email_not_verified', $harness->auditRecorder->entries[0]->outcome->reasonCode);
    }

    public function test_role_not_allowed_logs_out_the_guard_and_records_the_reason(): void
    {
        $harness = $this->harness(
            attemptSucceeds: true,
            credential: $this->credential(emailVerified: true),
            identity: $this->identity(role: 'clinic_owner'),
        );

        self::assertNull($harness->service->authenticate(
            'designer@example.test',
            'correct horse battery staple',
            new DateTimeImmutable('2026-07-19T10:00:00Z'),
        ));
        self::assertSame(0, $harness->store->establishCount);
        self::assertSame(1, $harness->guard->logoutCount);
        self::assertSame('role_not_allowed', $harness->auditRecorder->entries[0]->outcome->reasonCode);
    }

    private function harness(
        bool $attemptSucceeds,
        PlatformWorkforceCredentialData $credential,
        PlatformIdentityData $identity,
        bool $throwOnAuditRecord = false,
    ): AuthenticationAuditHarness {
        $store = new AuthenticationInMemoryPlatformSessionStore;
        $auditRecorder = new AuthenticationTrackingAuditEntryRecorder($throwOnAuditRecord);
        $logger = new AuthenticationTrackingLogger;
        $guard = new AuthenticationFakeStatefulGuard($attemptSucceeds);

        return new AuthenticationAuditHarness(
            new AuthenticatePlatformSessionService(
                new AuthenticationFakeAuthFactory($guard),
                new AuthenticationFixedPlatformWorkforceCredentialLookup($credential),
                new AuthenticationFixedPlatformIdentityLookup($identity),
                $store,
                $auditRecorder,
                new AuthenticationFixedAuditCorrelationIdResolver(self::CORRELATION_ID),
                $logger,
            ),
            $store,
            $auditRecorder,
            $logger,
            $guard,
        );
    }

    private function identity(string $status = 'active', string $role = 'website_designer'): PlatformIdentityData
    {
        return new PlatformIdentityData(
            self::IDENTITY_ID,
            'designer@example.test',
            'Website Designer',
            $role,
            $status,
        );
    }

    private function credential(
        bool $emailVerified = true,
        string $accountStatus = 'active',
        ?DateTimeImmutable $lockoutUntil = null,
    ): PlatformWorkforceCredentialData {
        return new PlatformWorkforceCredentialData(
            self::IDENTITY_ID,
            'designer@example.test',
            $emailVerified,
            $emailVerified ? new DateTimeImmutable('2026-07-19T09:00:00Z') : null,
            $accountStatus,
            0,
            $lockoutUntil,
            3,
            new DateTimeImmutable('2026-07-19T09:00:00Z'),
            new DateTimeImmutable('2026-07-19T09:15:00Z'),
        );
    }

    private const IDENTITY_ID = '00000000-0000-4000-8000-000000000111';

    private const CORRELATION_ID = '00000000-0000-4000-8000-000000000222';
}

final readonly class AuthenticationAuditHarness
{
    public function __construct(
        public AuthenticatePlatformSessionService $service,
        public AuthenticationInMemoryPlatformSessionStore $store,
        public AuthenticationTrackingAuditEntryRecorder $auditRecorder,
        public AuthenticationTrackingLogger $logger,
        public AuthenticationFakeStatefulGuard $guard,
    ) {}
}

final class AuthenticationInMemoryPlatformSessionStore implements PlatformSessionStoreInterface
{
    public int $establishCount = 0;

    public function establish(
        PlatformPrincipal $principal,
        DateTimeImmutable $authenticatedAt,
        bool $remember = false,
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

/** A minimal fake of Laravel's native stateful (session) Guard, used exactly as `Auth::guard('platform_identity')` would be. */
final class AuthenticationFakeStatefulGuard implements StatefulGuard
{
    public int $logoutCount = 0;

    /** @var array<string, mixed>|null */
    public ?array $lastAttemptCredentials = null;

    public function __construct(private readonly bool $attemptSucceeds) {}

    public function attempt(array $credentials = [], $remember = false): bool
    {
        $this->lastAttemptCredentials = $credentials;

        return $this->attemptSucceeds;
    }

    public function once(array $credentials = []): bool
    {
        return $this->attemptSucceeds;
    }

    public function login(Authenticatable $user, $remember = false): void {}

    public function loginUsingId($id, $remember = false): false
    {
        return false;
    }

    public function onceUsingId($id): false
    {
        return false;
    }

    public function viaRemember(): bool
    {
        return false;
    }

    public function logout(): void
    {
        $this->logoutCount++;
    }

    public function check(): bool
    {
        return false;
    }

    public function guest(): bool
    {
        return true;
    }

    public function user(): ?Authenticatable
    {
        return null;
    }

    public function id(): null
    {
        return null;
    }

    public function validate(array $credentials = []): bool
    {
        return $this->attemptSucceeds;
    }

    public function hasUser(): bool
    {
        return false;
    }

    public function setUser(Authenticatable $user): static
    {
        return $this;
    }
}

final readonly class AuthenticationFakeAuthFactory implements AuthFactory
{
    public function __construct(private AuthenticationFakeStatefulGuard $guard) {}

    public function guard($name = null): Guard
    {
        return $this->guard;
    }

    public function shouldUse($name): void {}
}

final class AuthenticationFixedPlatformWorkforceCredentialLookup implements PlatformWorkforceCredentialLookupInterface
{
    public function __construct(private PlatformWorkforceCredentialData $credential) {}

    public function findByNormalizedEmail(string $email): ?PlatformWorkforceCredentialData
    {
        return strtolower($email) === $this->credential->normalizedEmail ? $this->credential : null;
    }
}

final class AuthenticationFixedPlatformIdentityLookup implements PlatformIdentityLookupInterface
{
    public function __construct(private PlatformIdentityData $identity) {}

    public function findById(string $platformIdentityId): ?PlatformIdentityData
    {
        return $this->identity->id === $platformIdentityId ? $this->identity : null;
    }
}

final class AuthenticationFixedAuditCorrelationIdResolver implements AuditCorrelationIdResolverInterface
{
    public function __construct(private string $correlationId) {}

    public function resolve(): string
    {
        return $this->correlationId;
    }
}

final class AuthenticationTrackingAuditEntryRecorder implements AuditEntryRecorderInterface
{
    /** @var list<AuditEntryData> */
    public array $entries = [];

    public function __construct(private bool $throwOnRecord = false) {}

    public function record(AuditEntryData $auditEntry): AuditEntry
    {
        if ($this->throwOnRecord) {
            throw new RuntimeException('audit storage failed');
        }

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

    public function entriesCount(): int
    {
        return count($this->entries);
    }
}

final class AuthenticationTrackingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $criticalRecords = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if ($level !== 'critical') {
            return;
        }

        $this->criticalRecords[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    public function recordsCount(): int
    {
        return count($this->criticalRecords);
    }
}
