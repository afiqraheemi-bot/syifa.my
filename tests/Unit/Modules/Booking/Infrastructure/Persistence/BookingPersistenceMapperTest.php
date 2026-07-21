<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Booking\Infrastructure\Persistence;

use App\Modules\Booking\Domain\Booking;
use App\Modules\Booking\Domain\ValueObjects\AppointmentDate;
use App\Modules\Booking\Domain\ValueObjects\AppointmentTime;
use App\Modules\Booking\Domain\ValueObjects\BookingId;
use App\Modules\Booking\Domain\ValueObjects\BookingReference;
use App\Modules\Booking\Domain\ValueObjects\ClinicId;
use App\Modules\Booking\Domain\ValueObjects\PatientEmail;
use App\Modules\Booking\Domain\ValueObjects\PatientName;
use App\Modules\Booking\Domain\ValueObjects\PatientPhone;
use App\Modules\Booking\Domain\ValueObjects\TenantId;
use App\Modules\Booking\Infrastructure\Persistence\Mappers\BookingPersistenceMapper;
use App\Modules\Booking\Infrastructure\Persistence\Records\BookingStorageRecord;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class BookingPersistenceMapperTest extends TestCase
{
    public function test_maps_domain_to_storage_record(): void
    {
        $booking = $this->booking();
        $mapper = new BookingPersistenceMapper;

        $record = $mapper->record($booking);

        self::assertSame($this->uuid(1), $record->id);
        self::assertSame($this->uuid(2), $record->tenantId);
        self::assertSame($this->uuid(3), $record->clinicId);
        self::assertSame('BOOK-0001', $record->bookingReference);
        self::assertSame('submitted', $record->status);
        self::assertSame('Aisyah Rahman', $record->patientName);
        self::assertSame('+60123456789', $record->patientPhone);
        self::assertSame('aisyah@example.test', $record->patientEmail);
        self::assertSame('2026-08-01', $record->appointmentOn);
        self::assertSame('09:30', $record->appointmentTime);
        self::assertSame('First visit', $record->notes);
        self::assertSame(0, $record->version);
    }

    public function test_reconstitutes_immutable_domain_with_version(): void
    {
        $mapper = new BookingPersistenceMapper;
        $record = new BookingStorageRecord(
            $this->uuid(1),
            $this->uuid(2),
            $this->uuid(3),
            'BOOK-0001',
            'submitted',
            'Aisyah Rahman',
            '+60123456789',
            'aisyah@example.test',
            '2026-08-01',
            '09:30',
            'First visit',
            $this->occurredAt(),
            $this->occurredAt(),
            7,
        );

        $booking = $mapper->toDomain($record);

        self::assertSame($this->uuid(1), $booking->id->value);
        self::assertSame('submitted', $booking->status()->value);
        self::assertSame(7, $booking->version());
        self::assertSame('2026-08-01', $booking->appointmentDate->value);
        self::assertSame('09:30', $booking->appointmentTime->value);
    }

    public function test_reconstitutes_a_booking_without_email_or_notes(): void
    {
        $mapper = new BookingPersistenceMapper;
        $record = new BookingStorageRecord(
            $this->uuid(1),
            $this->uuid(2),
            $this->uuid(3),
            'BOOK-0001',
            'submitted',
            'Aisyah Rahman',
            '+60123456789',
            null,
            '2026-08-01',
            '09:30',
            null,
            $this->occurredAt(),
            $this->occurredAt(),
            1,
        );

        $booking = $mapper->toDomain($record);

        self::assertNull($booking->patientEmail);
        self::assertNull($booking->notes);
    }

    private function booking(): Booking
    {
        return Booking::submit(
            new BookingId($this->uuid(1)),
            new TenantId($this->uuid(2)),
            new ClinicId($this->uuid(3)),
            new BookingReference('BOOK-0001'),
            new PatientName('Aisyah Rahman'),
            new PatientPhone('+60123456789'),
            new PatientEmail('aisyah@example.test'),
            new AppointmentDate('2026-08-01'),
            new AppointmentTime('09:30'),
            'First visit',
            $this->occurredAt(),
        );
    }

    private function occurredAt(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-30T00:00:00Z');
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
