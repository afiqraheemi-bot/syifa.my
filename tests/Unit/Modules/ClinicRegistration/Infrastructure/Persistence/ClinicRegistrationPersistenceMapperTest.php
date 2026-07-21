<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\ClinicRegistration\Infrastructure\Persistence;

use App\Modules\ClinicRegistration\Domain\ClinicRegistration;
use App\Modules\ClinicRegistration\Domain\ValueObjects\ClinicRegistrationProfile;
use App\Modules\ClinicRegistration\Domain\ValueObjects\CommercialSelectionReference;
use App\Modules\ClinicRegistration\Domain\ValueObjects\DeclarationAcceptance;
use App\Modules\ClinicRegistration\Domain\ValueObjects\PlatformIdentityReference;
use App\Modules\ClinicRegistration\Domain\ValueObjects\RegistrationId;
use App\Modules\ClinicRegistration\Domain\ValueObjects\RegistrationStatus;
use App\Modules\ClinicRegistration\Domain\ValueObjects\TenantId;
use App\Modules\ClinicRegistration\Infrastructure\Persistence\Mappers\ClinicRegistrationPersistenceMapper;
use App\Modules\ClinicRegistration\Infrastructure\Persistence\Records\ClinicRegistrationStorageRecord;
use App\Modules\ClinicRegistration\Infrastructure\Persistence\Records\DeclarationAcceptanceStorageRecord;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ClinicRegistrationPersistenceMapperTest extends TestCase
{
    public function test_maps_domain_to_storage_records_without_add_on_state(): void
    {
        $registration = $this->registration();
        $mapper = new ClinicRegistrationPersistenceMapper;

        $record = $mapper->registrationRecord($registration);
        $declarations = $mapper->declarationRecords($registration);

        self::assertSame($this->uuid(1), $record->id);
        self::assertSame($this->uuid(2), $record->platformIdentityId);
        self::assertSame('submitted', $record->status);
        self::assertSame('offering-basic-monthly', $record->selectedPlanOfferingReference);
        self::assertSame('monthly', $record->selectedBillingOptionReference);
        self::assertCount(1, $declarations);
        self::assertSame($this->uuid(4), $record->reservedTenantId);
        self::assertObjectNotHasProperty('selectedAddOnReferences', $record);
    }

    public function test_reconstitutes_immutable_domain_with_version(): void
    {
        $mapper = new ClinicRegistrationPersistenceMapper;
        $record = new ClinicRegistrationStorageRecord(
            $this->uuid(1),
            $this->uuid(2),
            'submitted',
            'Klinik Syifa',
            'owner@clinic.test',
            '+60123456789',
            '1 Jalan Klinik',
            'offering-basic-monthly',
            'monthly',
            'catalogue-v1',
            $this->uuid(1),
            $this->uuid(4),
            null,
            $this->occurredAt(),
            null,
            null,
            null,
            7,
        );

        $registration = $mapper->toDomain($record, [
            new DeclarationAcceptanceStorageRecord($this->uuid(1), 'terms.acceptance', '2026-07-20', $this->occurredAt()),
        ]);

        self::assertSame(RegistrationStatus::Submitted, $registration->status);
        self::assertSame(7, $registration->version());
        self::assertSame($this->uuid(4), $registration->reservedTenantId?->value);
        self::assertSame([], $registration->releaseEvents());
    }

    private function registration(): ClinicRegistration
    {
        $registration = ClinicRegistration::start(
            new RegistrationId($this->uuid(1)),
            new PlatformIdentityReference($this->uuid(2)),
            $this->occurredAt(),
        );
        $registration->updateDraft(
            new ClinicRegistrationProfile('Klinik Syifa', 'owner@clinic.test', '+60123456789', '1 Jalan Klinik'),
            [new DeclarationAcceptance('terms.acceptance', '2026-07-20', $this->occurredAt())],
            new CommercialSelectionReference('offering-basic-monthly', 'monthly', 'catalogue-v1'),
        );
        $registration->submit(new TenantId($this->uuid(4)), $this->occurredAt());
        $registration->synchronizeVersion(2);

        return $registration;
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
