<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\ClinicRegistration\Application;

use App\Modules\ClinicRegistration\Application\Audit\ClinicRegistrationAuditTrail;
use App\Modules\ClinicRegistration\Application\CancelClinicRegistrationService;
use App\Modules\ClinicRegistration\Application\ClinicRegistrationDataAssembler;
use App\Modules\ClinicRegistration\Application\ClinicRegistrationIdentifierGeneratorInterface;
use App\Modules\ClinicRegistration\Application\CompleteClinicRegistrationFromTrustedHandoffService;
use App\Modules\ClinicRegistration\Application\Exceptions\ClinicRegistrationNotFoundException;
use App\Modules\ClinicRegistration\Application\Exceptions\ClinicRegistrationVersionMismatchException;
use App\Modules\ClinicRegistration\Application\Exceptions\UntrustedClinicRegistrationCompletionException;
use App\Modules\ClinicRegistration\Application\ExpireStaleClinicRegistrationService;
use App\Modules\ClinicRegistration\Application\StartClinicRegistrationService;
use App\Modules\ClinicRegistration\Application\SubmitClinicRegistrationService;
use App\Modules\ClinicRegistration\Application\TrustedCompletionSources;
use App\Modules\ClinicRegistration\Application\UpdateClinicRegistrationDraftService;
use App\Modules\ClinicRegistration\Application\ViewCurrentClinicRegistrationService;
use App\Modules\ClinicRegistration\Contracts\Commands\CancelClinicRegistrationCommand;
use App\Modules\ClinicRegistration\Contracts\Commands\CompleteClinicRegistrationFromTrustedHandoffCommand;
use App\Modules\ClinicRegistration\Contracts\Commands\ExpireStaleClinicRegistrationCommand;
use App\Modules\ClinicRegistration\Contracts\Commands\StartClinicRegistrationCommand;
use App\Modules\ClinicRegistration\Contracts\Commands\SubmitClinicRegistrationCommand;
use App\Modules\ClinicRegistration\Contracts\Commands\UpdateClinicRegistrationDraftCommand;
use App\Modules\ClinicRegistration\Contracts\Data\DeclarationAcceptanceData;
use App\Modules\ClinicRegistration\Contracts\Events\ClinicRegistrationEventPublisherInterface;
use App\Modules\ClinicRegistration\Contracts\Repositories\ClinicRegistrationRepositoryInterface;
use App\Modules\ClinicRegistration\Domain\ClinicRegistration;
use App\Modules\ClinicRegistration\Domain\ValueObjects\PlatformIdentityReference;
use App\Modules\ClinicRegistration\Domain\ValueObjects\RegistrationId;
use App\Modules\ClinicRegistration\Domain\ValueObjects\RegistrationStatus;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Domain\AuditEntry\AuditEntry;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditEntryId;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
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
        $submitted = (new SubmitClinicRegistrationService($repository, $assembler, $auditTrail, $events))->execute(
            new SubmitClinicRegistrationCommand($this->uuid(21), 2, $this->occurredAt(), $this->uuid(94)),
        );
        $viewed = (new ViewCurrentClinicRegistrationService($repository, $assembler))->execute($this->uuid(21));
        $cancelled = (new CancelClinicRegistrationService($repository, $assembler, $auditTrail, $events))->execute(
            new CancelClinicRegistrationCommand($this->uuid(21), 3, $this->occurredAt(), $this->uuid(95)),
        );

        self::assertSame('Klinik Syifa', $updated->clinicName);
        self::assertSame('submitted', $submitted->status);
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
        (new SubmitClinicRegistrationService($repository, $assembler, $auditTrail, $events))->execute(
            new SubmitClinicRegistrationCommand($this->uuid(21), 2, $this->occurredAt(), $this->uuid(98)),
        );

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

final class RecordingClinicRegistrationEventPublisher implements ClinicRegistrationEventPublisherInterface
{
    /** @var list<object> */
    public array $events = [];

    public function publish(array $events): void
    {
        array_push($this->events, ...$events);
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
                && in_array($registration->status, [RegistrationStatus::Draft, RegistrationStatus::Submitted], true)
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
