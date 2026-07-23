<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Identity;

use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditCorrelationIdResolverInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Domain\AuditEntry\AuditEntry;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditEntryId;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use App\Modules\TenantManagement\Contracts\Authentication\Signals\ClinicOwnerAuthenticationRejected;
use App\Modules\TenantManagement\Contracts\Authentication\Signals\ClinicOwnerAuthenticationSucceeded;
use App\Support\Identity\RecordClinicOwnerAuthenticationAuditEntryListener;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;

final class RecordClinicOwnerAuthenticationAuditEntryListenerTest extends TestCase
{
    public function test_a_successful_authentication_records_a_clinic_owner_actor_login_entry(): void
    {
        $recorder = new ClinicOwnerAuditTrackingRecorder;
        $listener = new RecordClinicOwnerAuthenticationAuditEntryListener(
            $recorder,
            new ClinicOwnerAuditFixedCorrelationIdResolver('10000000-0000-4000-8000-000000000001'),
            new ClinicOwnerAuditTrackingLogger,
        );

        $occurredAt = new DateTimeImmutable('2026-07-13T10:00:00+00:00');
        $listener->handleSucceeded(new ClinicOwnerAuthenticationSucceeded(
            '00000000-0000-4000-8000-000000000001',
            '00000000-0000-4000-8000-000000000002',
            '00000000-0000-4000-8000-000000000003',
            $occurredAt,
        ));

        self::assertCount(1, $recorder->entries);
        $entry = $recorder->entries[0];
        self::assertSame(AuditActorType::ClinicOwner->value, $entry->actor->type);
        self::assertSame('00000000-0000-4000-8000-000000000002', $entry->actor->identityId);
        self::assertSame('clinic_owner.authentication.login', $entry->action);
        self::assertSame(AuditOutcomeType::Succeeded->value, $entry->outcome->outcome);
        self::assertNull($entry->outcome->reasonCode);
        self::assertSame('10000000-0000-4000-8000-000000000001', $entry->correlationId);
        self::assertSame($occurredAt, $entry->occurredAt);
        self::assertStringNotContainsString('password', strtolower(json_encode($entry->safeMetadata) ?: ''));
    }

    public function test_a_rejected_authentication_records_an_anonymous_failed_entry_without_revealing_identity(): void
    {
        $recorder = new ClinicOwnerAuditTrackingRecorder;
        $listener = new RecordClinicOwnerAuthenticationAuditEntryListener(
            $recorder,
            new ClinicOwnerAuditFixedCorrelationIdResolver('10000000-0000-4000-8000-000000000002'),
            new ClinicOwnerAuditTrackingLogger,
        );

        $listener->handleRejected(new ClinicOwnerAuthenticationRejected(new DateTimeImmutable('2026-07-13T10:05:00+00:00')));

        self::assertCount(1, $recorder->entries);
        $entry = $recorder->entries[0];
        self::assertSame(AuditActorType::Anonymous->value, $entry->actor->type);
        self::assertNull($entry->actor->identityId);
        self::assertSame('clinic_owner.authentication.login', $entry->action);
        self::assertSame(AuditOutcomeType::Failed->value, $entry->outcome->outcome);
        self::assertSame('invalid_credentials', $entry->outcome->reasonCode);
        self::assertNull($entry->target->id);
    }

    public function test_an_audit_storage_failure_falls_back_to_an_emergency_security_log(): void
    {
        $logger = new ClinicOwnerAuditTrackingLogger;
        $listener = new RecordClinicOwnerAuthenticationAuditEntryListener(
            new ClinicOwnerAuditThrowingRecorder,
            new ClinicOwnerAuditFixedCorrelationIdResolver('10000000-0000-4000-8000-000000000003'),
            $logger,
        );

        $listener->handleSucceeded(new ClinicOwnerAuthenticationSucceeded(
            '00000000-0000-4000-8000-000000000001',
            '00000000-0000-4000-8000-000000000002',
            '00000000-0000-4000-8000-000000000003',
            new DateTimeImmutable,
        ));

        self::assertCount(1, $logger->criticalRecords);
        self::assertSame('tenant_management.security.audit.emergency', $logger->criticalRecords[0]['message']);
    }
}

final class ClinicOwnerAuditTrackingRecorder implements AuditEntryRecorderInterface
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

final class ClinicOwnerAuditThrowingRecorder implements AuditEntryRecorderInterface
{
    public function record(AuditEntryData $auditEntry): AuditEntry
    {
        throw new RuntimeException('audit storage failed');
    }
}

final readonly class ClinicOwnerAuditFixedCorrelationIdResolver implements AuditCorrelationIdResolverInterface
{
    public function __construct(private string $correlationId) {}

    public function resolve(): string
    {
        return $this->correlationId;
    }
}

final class ClinicOwnerAuditTrackingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $criticalRecords = [];

    public function log($level, string|Stringable $message, array $context = []): void
    {
        if ($level !== 'critical') {
            return;
        }

        $this->criticalRecords[] = ['level' => (string) $level, 'message' => (string) $message, 'context' => $context];
    }
}
