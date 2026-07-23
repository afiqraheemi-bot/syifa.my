<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\PlatformAdministration\Application\Authentication;

use App\Modules\PlatformAdministration\Application\Authentication\LogoutPlatformSessionService;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditCorrelationIdResolverInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipal;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipalResolverInterface;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformSessionState;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformSessionStoreInterface;
use App\Modules\PlatformAdministration\Domain\AuditEntry\AuditEntry;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditEntryId;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;

final class LogoutPlatformSessionServiceTest extends TestCase
{
    public function test_logout_invalidates_the_session_and_records_an_audit_entry(): void
    {
        $harness = $this->harness();

        $harness->service->execute();

        self::assertSame(1, $harness->store->invalidateCount);
        self::assertCount(1, $harness->auditRecorder->entries);

        $entry = $harness->auditRecorder->entries[0];
        self::assertSame('platform.authentication.logout', $entry->action);
        self::assertSame(AuditOutcomeType::Succeeded->value, $entry->outcome->outcome);
        self::assertSame(self::IDENTITY_ID, $entry->actor->identityId);
        self::assertSame('platform_session', $entry->target->type);
        self::assertSame(self::IDENTITY_ID, $entry->target->id);
        self::assertSame(self::CORRELATION_ID, $entry->correlationId);
        self::assertSame(['actor_role' => 'website_designer'], $entry->safeMetadata);
    }

    public function test_logout_audit_failure_still_invalidates_the_session_and_logs_emergency_security_data(): void
    {
        $harness = $this->harness(throwOnAuditRecord: true);

        $harness->service->execute();

        self::assertSame(1, $harness->store->invalidateCount);
        self::assertCount(1, $harness->logger->criticalRecords);
        self::assertSame('platform.security.audit.emergency', $harness->logger->criticalRecords[0]['message']);
        self::assertSame('platform.authentication.logout', $harness->logger->criticalRecords[0]['context']['action']);
        self::assertSame('succeeded', $harness->logger->criticalRecords[0]['context']['outcome']);
        self::assertSame('audit_entry_recording_failed', $harness->logger->criticalRecords[0]['context']['reason_code']);
    }

    public function test_logout_without_a_current_principal_still_invalidates_the_session(): void
    {
        $harness = $this->harnessWithoutPrincipal();

        $harness->service->execute();

        self::assertSame(1, $harness->store->invalidateCount);
        self::assertSame(AuditActorType::Anonymous->value, $harness->auditRecorder->entries[0]->actor->type);
        self::assertNull($harness->auditRecorder->entries[0]->actor->identityId);
    }

    private function harness(bool $throwOnAuditRecord = false): LogoutAuditHarness
    {
        $store = new InMemoryLogoutPlatformSessionStore;
        $principalResolver = new LogoutFixedPlatformPrincipalResolver(new PlatformPrincipal(
            self::IDENTITY_ID,
            'website_designer',
            'Website Designer',
        ));
        $auditRecorder = new LogoutTrackingAuditEntryRecorder($throwOnAuditRecord);
        $logger = new LogoutTrackingLogger;

        return new LogoutAuditHarness(
            new LogoutPlatformSessionService(
                $principalResolver,
                $store,
                $auditRecorder,
                new LogoutFixedAuditCorrelationIdResolver(self::CORRELATION_ID),
                $logger,
            ),
            $store,
            $auditRecorder,
            $logger,
        );
    }

    private function harnessWithoutPrincipal(bool $throwOnAuditRecord = false): LogoutAuditHarness
    {
        $store = new InMemoryLogoutPlatformSessionStore;
        $principalResolver = new LogoutFixedPlatformPrincipalResolver(null);
        $auditRecorder = new LogoutTrackingAuditEntryRecorder($throwOnAuditRecord);
        $logger = new LogoutTrackingLogger;

        return new LogoutAuditHarness(
            new LogoutPlatformSessionService(
                $principalResolver,
                $store,
                $auditRecorder,
                new LogoutFixedAuditCorrelationIdResolver(self::CORRELATION_ID),
                $logger,
            ),
            $store,
            $auditRecorder,
            $logger,
        );
    }

    private const IDENTITY_ID = '00000000-0000-4000-8000-000000000333';

    private const CORRELATION_ID = '00000000-0000-4000-8000-000000000334';
}

final readonly class LogoutAuditHarness
{
    public function __construct(
        public LogoutPlatformSessionService $service,
        public InMemoryLogoutPlatformSessionStore $store,
        public LogoutTrackingAuditEntryRecorder $auditRecorder,
        public LogoutTrackingLogger $logger,
    ) {}
}

final class LogoutFixedPlatformPrincipalResolver implements PlatformPrincipalResolverInterface
{
    public function __construct(private ?PlatformPrincipal $principal) {}

    public function resolve(DateTimeImmutable $resolvedAt): ?PlatformPrincipal
    {
        return $this->principal;
    }
}

final class InMemoryLogoutPlatformSessionStore implements PlatformSessionStoreInterface
{
    public int $invalidateCount = 0;

    public function establish(PlatformPrincipal $principal, DateTimeImmutable $authenticatedAt, bool $remember = false): PlatformSessionState
    {
        throw new RuntimeException('Not expected in this test.');
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
        $this->invalidateCount++;
    }
}

final class LogoutTrackingAuditEntryRecorder implements AuditEntryRecorderInterface
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
}

final class LogoutFixedAuditCorrelationIdResolver implements AuditCorrelationIdResolverInterface
{
    public function __construct(private string $correlationId) {}

    public function resolve(): string
    {
        return $this->correlationId;
    }
}

final class LogoutTrackingLogger extends AbstractLogger
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
}
