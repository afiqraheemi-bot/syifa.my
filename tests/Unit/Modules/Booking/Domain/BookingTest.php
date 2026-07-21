<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Booking\Domain;

use App\Modules\Booking\Domain\Booking;
use App\Modules\Booking\Domain\Exceptions\InvalidBookingValueException;
use App\Modules\Booking\Domain\ValueObjects\AppointmentDate;
use App\Modules\Booking\Domain\ValueObjects\AppointmentTime;
use App\Modules\Booking\Domain\ValueObjects\BookingId;
use App\Modules\Booking\Domain\ValueObjects\BookingReference;
use App\Modules\Booking\Domain\ValueObjects\BookingStatus;
use App\Modules\Booking\Domain\ValueObjects\ClinicId;
use App\Modules\Booking\Domain\ValueObjects\PatientEmail;
use App\Modules\Booking\Domain\ValueObjects\PatientName;
use App\Modules\Booking\Domain\ValueObjects\PatientPhone;
use App\Modules\Booking\Domain\ValueObjects\TenantId;
use DateTimeImmutable;
use Error;
use PHPUnit\Framework\TestCase;

final class BookingTest extends TestCase
{
    public function test_submit_creates_a_booking_in_the_submitted_status(): void
    {
        $booking = $this->booking();

        self::assertSame(BookingStatus::Submitted, $booking->status());
        self::assertSame($this->uuid(1), $booking->id->value);
        self::assertSame($this->uuid(2), $booking->tenantId->value);
        self::assertSame($this->uuid(3), $booking->clinicId->value);
        self::assertSame('BOOK-0001', $booking->reference->value);
        self::assertSame('Aisyah Rahman', $booking->patientName->value);
        self::assertSame('+60123456789', $booking->patientPhone->value);
        self::assertSame('aisyah@example.test', $booking->patientEmail?->value);
        self::assertSame('2026-08-01', $booking->appointmentDate->value);
        self::assertSame('09:30', $booking->appointmentTime->value);
        self::assertSame('First visit', $booking->notes);
        self::assertSame(0, $booking->version());
    }

    public function test_patient_email_and_notes_are_optional(): void
    {
        $booking = $this->booking(patientEmail: null, notes: null);

        self::assertNull($booking->patientEmail);
        self::assertNull($booking->notes);
    }

    public function test_created_at_and_updated_at_start_equal_at_submission(): void
    {
        $booking = $this->booking();

        self::assertSame($this->occurredAt()->format(DATE_ATOM), $booking->createdAt->format(DATE_ATOM));
        self::assertSame($this->occurredAt()->format(DATE_ATOM), $booking->updatedAt()->format(DATE_ATOM));
    }

    public function test_identity_fields_are_immutable(): void
    {
        $booking = $this->booking();

        $this->expectException(Error::class);

        // @phpstan-ignore-next-line - proving readonly identity is language-enforced.
        $booking->id = new BookingId($this->uuid(9));
    }

    public function test_version_can_be_synchronized_for_optimistic_concurrency(): void
    {
        $booking = $this->booking();

        $booking->synchronizeVersion(4);

        self::assertSame(4, $booking->version());
    }

    public function test_booking_id_rejects_a_non_uuid_value(): void
    {
        $this->expectException(InvalidBookingValueException::class);

        new BookingId('not-a-uuid');
    }

    public function test_tenant_id_rejects_a_non_uuid_value(): void
    {
        $this->expectException(InvalidBookingValueException::class);

        new TenantId('not-a-uuid');
    }

    public function test_clinic_id_rejects_a_non_uuid_value(): void
    {
        $this->expectException(InvalidBookingValueException::class);

        new ClinicId('not-a-uuid');
    }

    public function test_booking_reference_rejects_a_blank_value(): void
    {
        $this->expectException(InvalidBookingValueException::class);

        new BookingReference('   ');
    }

    public function test_patient_name_rejects_a_blank_value(): void
    {
        $this->expectException(InvalidBookingValueException::class);

        new PatientName('');
    }

    public function test_patient_phone_rejects_a_blank_value(): void
    {
        $this->expectException(InvalidBookingValueException::class);

        new PatientPhone('');
    }

    public function test_patient_email_rejects_a_blank_value(): void
    {
        $this->expectException(InvalidBookingValueException::class);

        new PatientEmail('');
    }

    public function test_appointment_date_rejects_a_malformed_value(): void
    {
        $this->expectException(InvalidBookingValueException::class);

        new AppointmentDate('2026-13-40');
    }

    public function test_appointment_date_rejects_a_non_calendar_value(): void
    {
        $this->expectException(InvalidBookingValueException::class);

        new AppointmentDate('01-08-2026');
    }

    public function test_appointment_time_rejects_a_malformed_value(): void
    {
        $this->expectException(InvalidBookingValueException::class);

        new AppointmentTime('25:99');
    }

    public function test_appointment_time_rejects_seconds_precision(): void
    {
        $this->expectException(InvalidBookingValueException::class);

        new AppointmentTime('09:30:00');
    }

    private function booking(?string $patientEmail = 'aisyah@example.test', ?string $notes = 'First visit'): Booking
    {
        return Booking::submit(
            new BookingId($this->uuid(1)),
            new TenantId($this->uuid(2)),
            new ClinicId($this->uuid(3)),
            new BookingReference('BOOK-0001'),
            new PatientName('Aisyah Rahman'),
            new PatientPhone('+60123456789'),
            $patientEmail === null ? null : new PatientEmail($patientEmail),
            new AppointmentDate('2026-08-01'),
            new AppointmentTime('09:30'),
            $notes,
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
