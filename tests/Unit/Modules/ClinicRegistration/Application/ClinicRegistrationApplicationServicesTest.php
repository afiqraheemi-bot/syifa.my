<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\ClinicRegistration\Application;

use App\Modules\ClinicRegistration\Application\ArchiveClinicRegistrationService;
use App\Modules\ClinicRegistration\Application\Audit\ClinicRegistrationAuditTrail;
use App\Modules\ClinicRegistration\Application\CancelClinicRegistrationService;
use App\Modules\ClinicRegistration\Application\ClinicRegistrationDataAssembler;
use App\Modules\ClinicRegistration\Application\ClinicRegistrationIdentifierGeneratorInterface;
use App\Modules\ClinicRegistration\Application\ClinicRegistrationTenantIdGeneratorInterface;
use App\Modules\ClinicRegistration\Application\CompleteClinicRegistrationFromTrustedHandoffService;
use App\Modules\ClinicRegistration\Application\DecideClinicRegistrationService;
use App\Modules\ClinicRegistration\Application\Exceptions\ClinicRegistrationNotFoundException;
use App\Modules\ClinicRegistration\Application\Exceptions\ClinicRegistrationVersionMismatchException;
use App\Modules\ClinicRegistration\Application\Exceptions\UntrustedClinicRegistrationCompletionException;
use App\Modules\ClinicRegistration\Application\ExpireStaleClinicRegistrationService;
use App\Modules\ClinicRegistration\Application\StartClinicRegistrationReviewService;
use App\Modules\ClinicRegistration\Application\StartClinicRegistrationService;
use App\Modules\ClinicRegistration\Application\SubmitClinicRegistrationService;
use App\Modules\ClinicRegistration\Application\TrustedCompletionSources;
use App\Modules\ClinicRegistration\Application\UpdateClinicRegistrationByAdministratorService;
use App\Modules\ClinicRegistration\Application\UpdateClinicRegistrationDraftService;
use App\Modules\ClinicRegistration\Application\ViewCurrentClinicRegistrationService;
use App\Modules\ClinicRegistration\Contracts\Administration\ClinicRegistrationAdministrationRepositoryInterface;
use App\Modules\ClinicRegistration\Contracts\Commands\ArchiveClinicRegistrationCommand;
use App\Modules\ClinicRegistration\Contracts\Commands\CancelClinicRegistrationCommand;
use App\Modules\ClinicRegistration\Contracts\Commands\CompleteClinicRegistrationFromTrustedHandoffCommand;
use App\Modules\ClinicRegistration\Contracts\Commands\DecideClinicRegistrationCommand;
use App\Modules\ClinicRegistration\Contracts\Commands\ExpireStaleClinicRegistrationCommand;
use App\Modules\ClinicRegistration\Contracts\Commands\StartClinicRegistrationCommand;
use App\Modules\ClinicRegistration\Contracts\Commands\StartClinicRegistrationReviewCommand;
use App\Modules\ClinicRegistration\Contracts\Commands\SubmitClinicRegistrationCommand;
use App\Modules\ClinicRegistration\Contracts\Commands\UpdateClinicRegistrationByAdministratorCommand;
use App\Modules\ClinicRegistration\Contracts\Commands\UpdateClinicRegistrationDraftCommand;
use App\Modules\ClinicRegistration\Contracts\Data\DeclarationAcceptanceData;
use App\Modules\ClinicRegistration\Contracts\Events\ClinicRegistrationEventPublisherInterface;
use App\Modules\ClinicRegistration\Contracts\Repositories\ClinicRegistrationRepositoryInterface;
use App\Modules\ClinicRegistration\Contracts\Review\ClinicRegistrationDecisionTransactionInterface;
use App\Modules\ClinicRegistration\Contracts\Review\ClinicRegistrationReviewAuditInterface;
use App\Modules\ClinicRegistration\Domain\ClinicRegistration;
use App\Modules\ClinicRegistration\Domain\ValueObjects\PlatformIdentityReference;
use App\Modules\ClinicRegistration\Domain\ValueObjects\RegistrationDecisionOutcome;
use App\Modules\ClinicRegistration\Domain\ValueObjects\RegistrationId;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Domain\AuditEntry\AuditEntry;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditEntryId;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use Closure;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ClinicRegistrationApplicationServicesTest extends TestCase
{
    public function test_start_is_idempotent_for_current_identity_and_records_audit_once(): void
    {
        $repository = new InMemoryClinicRegistrationRepository;
        $audit = new RecordingAuditEntryRecorder;
        $events = new RecordingClinicRegistrationEventPublisher;
        $service = new StartClinicRegistrationService(
            new SequentialIdentifierGenerator([$this->uuid(10)]),
            $repository,
            new ClinicRegistrationDataAssembler,
            new ClinicRegistrationAuditTrail($audit),
            $events,
        );

        $first = $service->execute(new StartClinicRegistrationCommand($this->uuid(20), $this->occurredAt(), $this->uuid(90)));
        $second = $service->execute(new StartClinicRegistrationCommand($this->uuid(20), $this->occurredAt(), $this->uuid(91)));

        self::assertSame($first->id, $second->id);
        self::assertSame('draft', $first->status);
        self::assertSame(1, $repository->saveCalls);
        self::assertSame(['clinic_registration.start'], array_map(
            static fn (AuditEntryData $entry): string => $entry->action,
            $audit->entries,
        ));
        self::assertCount(1, $events->events);
    }

    public function test_update_submit_cancel_and_view_use_current_owner_boundary(): void
    {
        $repository = new InMemoryClinicRegistrationRepository;
        $audit = new RecordingAuditEntryRecorder;
        $events = new RecordingClinicRegistrationEventPublisher;
        $assembler = new ClinicRegistrationDataAssembler;
        $auditTrail = new ClinicRegistrationAuditTrail($audit);

        (new StartClinicRegistrationService(
            new SequentialIdentifierGenerator([$this->uuid(11)]),
            $repository,
            $assembler,
            $auditTrail,
            $events,
        ))->execute(new StartClinicRegistrationCommand($this->uuid(21), $this->occurredAt(), $this->uuid(92)));

        $updated = (new UpdateClinicRegistrationDraftService($repository, $assembler, $auditTrail, $events))->execute(
            $this->updateCommand(expectedVersion: 1),
        );
        $submitted = (new SubmitClinicRegistrationService($repository, new SequentialTenantIdGenerator([$this->uuid(30)]), $assembler, $auditTrail, $events))->execute(
            new SubmitClinicRegistrationCommand($this->uuid(21), 2, $this->occurredAt(), $this->uuid(94)),
        );
        $viewed = (new ViewCurrentClinicRegistrationService($repository, $assembler))->execute($this->uuid(21));
        $cancelled = (new CancelClinicRegistrationService($repository, $assembler, $auditTrail, $events))->execute(
            new CancelClinicRegistrationCommand($this->uuid(21), 3, $this->occurredAt(), $this->uuid(95)),
        );

        self::assertSame('Klinik Syifa', $updated->clinicName);
        self::assertSame('submitted', $submitted->status);
        self::assertSame($this->uuid(30), $submitted->reservedTenantId);
        self::assertSame($submitted->id, $viewed?->id);
        self::assertSame('cancelled', $cancelled->status);
        self::assertSame([
            'clinic_registration.start',
            'clinic_registration.update',
            'clinic_registration.submit',
            'clinic_registration.cancel',
        ], array_map(static fn (AuditEntryData $entry): string => $entry->action, $audit->entries));
    }

    public function test_version_mismatch_rejects_stale_application_mutation(): void
    {
        $repository = new InMemoryClinicRegistrationRepository;
        $service = $this->startService($repository);
        $service->execute(new StartClinicRegistrationCommand($this->uuid(21), $this->occurredAt(), $this->uuid(96)));

        $this->expectException(ClinicRegistrationVersionMismatchException::class);

        (new UpdateClinicRegistrationDraftService(
            $repository,
            new ClinicRegistrationDataAssembler,
            new ClinicRegistrationAuditTrail(new RecordingAuditEntryRecorder),
            new RecordingClinicRegistrationEventPublisher,
        ))->execute($this->updateCommand(expectedVersion: 99));
    }

    public function test_cross_owner_update_uses_identity_bound_lookup(): void
    {
        $repository = new InMemoryClinicRegistrationRepository;

        $this->expectException(ClinicRegistrationNotFoundException::class);

        (new UpdateClinicRegistrationDraftService(
            $repository,
            new ClinicRegistrationDataAssembler,
            new ClinicRegistrationAuditTrail(new RecordingAuditEntryRecorder),
            new RecordingClinicRegistrationEventPublisher,
        ))->execute($this->updateCommand(expectedVersion: 1, platformIdentityId: $this->uuid(404)));
    }

    public function test_trusted_completion_provisions_registration_and_untrusted_source_is_rejected(): void
    {
        $repository = new InMemoryClinicRegistrationRepository;
        $audit = new RecordingAuditEntryRecorder;
        $events = new RecordingClinicRegistrationEventPublisher;
        $assembler = new ClinicRegistrationDataAssembler;
        $auditTrail = new ClinicRegistrationAuditTrail($audit);

        $this->startService($repository, $audit, $events)->execute(
            new StartClinicRegistrationCommand($this->uuid(21), $this->occurredAt(), $this->uuid(97)),
        );
        (new UpdateClinicRegistrationDraftService($repository, $assembler, $auditTrail, $events))->execute($this->updateCommand(expectedVersion: 1));
        (new SubmitClinicRegistrationService($repository, new SequentialTenantIdGenerator([$this->uuid(31)]), $assembler, $auditTrail, $events))->execute(
            new SubmitClinicRegistrationCommand($this->uuid(21), 2, $this->occurredAt(), $this->uuid(98)),
        );
        $registration = $repository->find(new RegistrationId($this->uuid(12)));
        self::assertNotNull($registration);
        $registration->startReview($this->uuid(8), $this->occurredAt());
        $registration->decide(
            $this->uuid(9),
            RegistrationDecisionOutcome::Approved,
            'eligible_clinic',
            null,
            $this->uuid(8),
            $this->occurredAt(),
        );
        $repository->save($registration);

        $completion = new CompleteClinicRegistrationFromTrustedHandoffService(
            $repository,
            $assembler,
            $auditTrail,
            $events,
            new TrustedCompletionSources(['tenant_management']),
        );
        $completed = $completion->execute(new CompleteClinicRegistrationFromTrustedHandoffCommand(
            $this->uuid(12),
            'tenant-reference-1',
            'tenant_management',
            $this->occurredAt(),
            $this->uuid(99),
        ));

        self::assertSame('provisioned', $completed->status);
        self::assertSame('tenant-reference-1', $completed->provisionedTenantReference);
        self::assertContains('clinic_registration.complete', array_map(static fn (AuditEntryData $entry): string => $entry->action, $audit->entries));

        $this->expectException(UntrustedClinicRegistrationCompletionException::class);

        $completion->execute(new CompleteClinicRegistrationFromTrustedHandoffCommand(
            $this->uuid(12),
            'tenant-reference-2',
            'untrusted',
            $this->occurredAt(),
            $this->uuid(100),
        ));
    }

    public function test_submit_retry_after_success_does_not_generate_a_different_tenant_id(): void
    {
        $repository = new InMemoryClinicRegistrationRepository;
        $audit = new RecordingAuditEntryRecorder;
        $events = new RecordingClinicRegistrationEventPublisher;
        $assembler = new ClinicRegistrationDataAssembler;
        $auditTrail = new ClinicRegistrationAuditTrail($audit);

        $this->startService($repository, $audit, $events)->execute(
            new StartClinicRegistrationCommand($this->uuid(21), $this->occurredAt(), $this->uuid(103)),
        );
        (new UpdateClinicRegistrationDraftService($repository, $assembler, $auditTrail, $events))->execute($this->updateCommand(expectedVersion: 1));

        $tenantIds = new SequentialTenantIdGenerator([$this->uuid(40), $this->uuid(41)]);
        $service = new SubmitClinicRegistrationService($repository, $tenantIds, $assembler, $auditTrail, $events);
        $submitted = $service->execute(new SubmitClinicRegistrationCommand($this->uuid(21), 2, $this->occurredAt(), $this->uuid(104)));

        self::assertSame($this->uuid(40), $submitted->reservedTenantId);

        $this->expectException(ClinicRegistrationVersionMismatchException::class);

        try {
            $service->execute(new SubmitClinicRegistrationCommand($this->uuid(21), 2, $this->occurredAt(), $this->uuid(105)));
        } finally {
            $stored = $repository->find(new RegistrationId($submitted->id));
            self::assertSame($this->uuid(40), $stored?->reservedTenantId?->value);
        }
    }

    public function test_super_admin_review_and_decision_services_enforce_version_and_record_audit(): void
    {
        $repository = new InMemoryClinicRegistrationRepository;
        $audit = new RecordingAuditEntryRecorder;
        $events = new RecordingClinicRegistrationEventPublisher;
        $assembler = new ClinicRegistrationDataAssembler;
        $auditTrail = new ClinicRegistrationAuditTrail($audit);
        $this->startService($repository, $audit, $events)->execute(
            new StartClinicRegistrationCommand($this->uuid(21), $this->occurredAt(), $this->uuid(97)),
        );
        (new UpdateClinicRegistrationDraftService($repository, $assembler, $auditTrail, $events))
            ->execute($this->updateCommand(expectedVersion: 1));
        (new SubmitClinicRegistrationService(
            $repository,
            new SequentialTenantIdGenerator([$this->uuid(31)]),
            $assembler,
            $auditTrail,
            $events,
        ))->execute(new SubmitClinicRegistrationCommand(
            $this->uuid(21),
            2,
            $this->occurredAt(),
            $this->uuid(98),
        ));

        $reviewAudit = new RecordingRegistrationReviewAudit;
        $transaction = new ImmediateRegistrationDecisionTransaction;
        $version = (new StartClinicRegistrationReviewService($repository, $transaction, $reviewAudit))
            ->execute(new StartClinicRegistrationReviewCommand(
                $this->uuid(12),
                3,
                $this->uuid(8),
                $this->uuid(101),
                $this->occurredAt(),
            ));
        self::assertSame(4, $version);

        $version = (new DecideClinicRegistrationService($repository, $transaction, $reviewAudit))
            ->execute(new DecideClinicRegistrationCommand(
                $this->uuid(12),
                $this->uuid(9),
                'approved',
                'eligible_clinic',
                null,
                4,
                $this->uuid(8),
                $this->uuid(102),
                $this->occurredAt(),
            ));
        self::assertSame(5, $version);
        self::assertSame('approved', $repository->find(new RegistrationId($this->uuid(12)))?->status->value);
        self::assertSame([
            'clinic_registration.review.start',
            'clinic_registration.decision.record',
        ], $reviewAudit->actions);
        self::assertSame(2, $transaction->calls);
    }

    public function test_expire_stale_registration_by_identifier(): void
    {
        $repository = new InMemoryClinicRegistrationRepository;
        $audit = new RecordingAuditEntryRecorder;
        $events = new RecordingClinicRegistrationEventPublisher;
        $registration = $this->startService($repository, $audit, $events)->execute(
            new StartClinicRegistrationCommand($this->uuid(21), $this->occurredAt(), $this->uuid(101)),
        );

        $expired = (new ExpireStaleClinicRegistrationService(
            $repository,
            new ClinicRegistrationDataAssembler,
            new ClinicRegistrationAuditTrail($audit),
            $events,
        ))->execute(new ExpireStaleClinicRegistrationCommand($registration->id, 1, $this->occurredAt(), $this->uuid(102)));

        self::assertSame('expired', $expired->status);
        self::assertContains('clinic_registration.expire', array_map(static fn (AuditEntryData $entry): string => $entry->action, $audit->entries));
    }

    public function test_super_admin_can_update_registration_profile_and_access_email_atomically(): void
    {
        $repository = new InMemoryClinicRegistrationRepository;
        $this->startService($repository)->execute(
            new StartClinicRegistrationCommand($this->uuid(21), $this->occurredAt(), $this->uuid(110)),
        );
        $administration = new RecordingClinicRegistrationAdministrationRepository;
        $audit = new RecordingRegistrationReviewAudit;

        $version = (new UpdateClinicRegistrationByAdministratorService(
            $repository,
            $administration,
            new ImmediateRegistrationDecisionTransaction,
            $audit,
        ))->execute(new UpdateClinicRegistrationByAdministratorCommand(
            $this->uuid(12),
            'Klinik Syifa Utama',
            ' OWNER@CLINIC.TEST ',
            '+60129999999',
            '2 Jalan Klinik',
            1,
            $this->uuid(8),
            $this->uuid(111),
            $this->occurredAt(),
        ));

        self::assertSame(2, $version);
        self::assertSame('Klinik Syifa Utama', $repository->find(new RegistrationId($this->uuid(12)))?->profile->clinicName);
        self::assertSame([$this->uuid(12) => 'owner@clinic.test'], $administration->emails);
        self::assertSame(['clinic_registration.administration.update'], $audit->actions);
    }

    public function test_super_admin_archive_uses_soft_archive_repository_and_durable_audit(): void
    {
        $repository = new InMemoryClinicRegistrationRepository;
        $this->startService($repository)->execute(
            new StartClinicRegistrationCommand($this->uuid(21), $this->occurredAt(), $this->uuid(112)),
        );
        $administration = new RecordingClinicRegistrationAdministrationRepository;
        $audit = new RecordingRegistrationReviewAudit;

        $version = (new ArchiveClinicRegistrationService(
            $repository,
            $administration,
            new ImmediateRegistrationDecisionTransaction,
            $audit,
        ))->execute(new ArchiveClinicRegistrationCommand(
            $this->uuid(12),
            1,
            $this->uuid(8),
            $this->uuid(113),
            $this->occurredAt(),
        ));

        self::assertSame(2, $version);
        self::assertSame([$this->uuid(12)], $administration->archived);
        self::assertSame([$this->uuid(12)], $administration->revokedAccess);
        self::assertSame(['clinic_registration.administration.archive'], $audit->actions);
    }

    private function startService(
        InMemoryClinicRegistrationRepository $repository,
        ?RecordingAuditEntryRecorder $audit = null,
        ?RecordingClinicRegistrationEventPublisher $events = null,
    ): StartClinicRegistrationService {
        return new StartClinicRegistrationService(
            new SequentialIdentifierGenerator([$this->uuid(12)]),
            $repository,
            new ClinicRegistrationDataAssembler,
            new ClinicRegistrationAuditTrail($audit ?? new RecordingAuditEntryRecorder),
            $events ?? new RecordingClinicRegistrationEventPublisher,
        );
    }

    private function updateCommand(int $expectedVersion, string $platformIdentityId = '00000000-0000-4000-8000-000000000021'): UpdateClinicRegistrationDraftCommand
    {
        return new UpdateClinicRegistrationDraftCommand(
            $platformIdentityId,
            'Klinik Syifa',
            'owner@clinic.test',
            '+60123456789',
            '1 Jalan Klinik',
            'offering-basic-monthly',
            'monthly',
            'catalogue-v1',
            $expectedVersion,
            $this->occurredAt(),
            $this->uuid(93),
            [new DeclarationAcceptanceData('terms.acceptance', '2026-07-20', '2026-07-20T00:00:00Z')],
        );
    }

    private function occurredAt(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-20T00:00:00Z');
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}

final class SequentialIdentifierGenerator implements ClinicRegistrationIdentifierGeneratorInterface
{
    /** @param list<string> $ids */
    public function __construct(private array $ids) {}

    public function generate(): string
    {
        return array_shift($this->ids) ?? '00000000-0000-4000-8000-000000999999';
    }
}

final class SequentialTenantIdGenerator implements ClinicRegistrationTenantIdGeneratorInterface
{
    /** @param list<string> $ids */
    public function __construct(private array $ids) {}

    public function generate(): string
    {
        return array_shift($this->ids) ?? '00000000-0000-4000-8000-000000899999';
    }
}

final class RecordingClinicRegistrationEventPublisher implements ClinicRegistrationEventPublisherInterface
{
    /** @var list<object> */
    public array $events = [];

    public function publish(array $events): void
    {
        array_push($this->events, ...$events);
    }
}

final class ImmediateRegistrationDecisionTransaction implements ClinicRegistrationDecisionTransactionInterface
{
    public int $calls = 0;

    public function run(Closure $operation): mixed
    {
        $this->calls++;

        return $operation();
    }
}

final class RecordingRegistrationReviewAudit implements ClinicRegistrationReviewAuditInterface
{
    /** @var list<string> */
    public array $actions = [];

    public function record(
        string $auditEntryId,
        string $actorPlatformIdentityId,
        string $registrationId,
        string $action,
        string $outcome,
        int $resultingVersion,
        string $correlationId,
        DateTimeImmutable $occurredAt,
    ): void {
        $this->actions[] = $action;
    }
}

final class RecordingClinicRegistrationAdministrationRepository implements ClinicRegistrationAdministrationRepositoryInterface
{
    /** @var array<string, string> */
    public array $emails = [];

    /** @var list<string> */
    public array $archived = [];

    /** @var list<string> */
    public array $revokedAccess = [];

    public function synchronizeAccessEmail(string $registrationId, string $normalizedEmail): void
    {
        $this->emails[$registrationId] = $normalizedEmail;
    }

    public function revokeAccess(string $registrationId): void
    {
        $this->revokedAccess[] = $registrationId;
    }

    public function archive(
        string $registrationId,
        int $expectedVersion,
        string $actorPlatformIdentityId,
        DateTimeImmutable $occurredAt,
    ): int {
        $this->archived[] = $registrationId;

        return $expectedVersion + 1;
    }
}

final class RecordingAuditEntryRecorder implements AuditEntryRecorderInterface
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

final class InMemoryClinicRegistrationRepository implements ClinicRegistrationRepositoryInterface
{
    /** @var array<string, ClinicRegistration> */
    private array $registrations = [];

    public int $saveCalls = 0;

    public function find(RegistrationId $registrationId): ?ClinicRegistration
    {
        return $this->registrations[$registrationId->value] ?? null;
    }

    public function findCurrentForPlatformIdentity(PlatformIdentityReference $platformIdentity): ?ClinicRegistration
    {
        foreach ($this->registrations as $registration) {
            if (
                $registration->platformIdentity->value === $platformIdentity->value
            ) {
                return $registration;
            }
        }

        return null;
    }

    public function findByCorrelationReference(string $correlationReference): ?ClinicRegistration
    {
        foreach ($this->registrations as $registration) {
            if ($registration->correlationReference === $correlationReference) {
                return $registration;
            }
        }

        return null;
    }

    public function save(ClinicRegistration $registration): void
    {
        $this->saveCalls++;
        $registration->synchronizeVersion($registration->version() + 1);
        $this->registrations[$registration->id->value] = $registration;
    }
}
