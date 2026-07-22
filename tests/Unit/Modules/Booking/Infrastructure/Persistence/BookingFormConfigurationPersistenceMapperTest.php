<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Booking\Infrastructure\Persistence;

use App\Modules\Booking\Domain\BookingFormConfiguration;
use App\Modules\Booking\Domain\ValueObjects\BookingFormField;
use App\Modules\Booking\Domain\ValueObjects\FieldLabels;
use App\Modules\Booking\Domain\ValueObjects\FieldOrder;
use App\Modules\Booking\Domain\ValueObjects\RequiredFields;
use App\Modules\Booking\Domain\ValueObjects\TenantId;
use App\Modules\Booking\Infrastructure\Persistence\Mappers\BookingFormConfigurationPersistenceMapper;
use App\Modules\Booking\Infrastructure\Persistence\Records\BookingFormConfigurationStorageRecord;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class BookingFormConfigurationPersistenceMapperTest extends TestCase
{
    public function test_maps_domain_to_storage_record(): void
    {
        $configuration = $this->configuration();
        $mapper = new BookingFormConfigurationPersistenceMapper;

        $record = $mapper->record($configuration);

        self::assertSame($this->uuid(1), $record->tenantId);
        self::assertTrue($record->enableServiceSelection);
        self::assertFalse($record->enableDoctorSelection);
        self::assertSame(['patient_name', 'phone'], $record->requiredFields);
        self::assertSame(['patient_name', 'phone', 'appointment_date', 'appointment_time'], $record->fieldOrder);
        self::assertSame(['notes' => 'Additional Notes'], $record->fieldLabels);
        self::assertSame(0, $record->version);
    }

    public function test_reconstitutes_immutable_domain_with_version(): void
    {
        $mapper = new BookingFormConfigurationPersistenceMapper;
        $record = new BookingFormConfigurationStorageRecord(
            $this->uuid(1),
            true,
            false,
            true,
            false,
            true,
            ['patient_name'],
            ['patient_name', 'phone', 'appointment_date', 'appointment_time'],
            ['notes' => 'Additional Notes'],
            $this->occurredAt(),
            $this->occurredAt(),
            4,
        );

        $configuration = $mapper->toDomain($record);

        self::assertSame($this->uuid(1), $configuration->tenantId->value);
        self::assertSame(4, $configuration->version());
        self::assertSame(['patient_name'], $configuration->requiredFields()->values());
        self::assertSame('Additional Notes', $configuration->fieldLabels()->labelFor(BookingFormField::Notes));
    }

    private function configuration(): BookingFormConfiguration
    {
        return BookingFormConfiguration::create(
            new TenantId($this->uuid(1)),
            true,
            false,
            true,
            false,
            true,
            new RequiredFields([BookingFormField::PatientName, BookingFormField::Phone]),
            new FieldOrder([
                BookingFormField::PatientName,
                BookingFormField::Phone,
                BookingFormField::AppointmentDate,
                BookingFormField::AppointmentTime,
            ]),
            new FieldLabels(['notes' => 'Additional Notes']),
            $this->occurredAt(),
        );
    }

    private function occurredAt(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-01T00:00:00Z');
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
