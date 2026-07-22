<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain;

use App\Modules\Booking\Domain\Exceptions\InvalidBookingValueException;
use App\Modules\Booking\Domain\ValueObjects\AppointmentDate;
use App\Modules\Booking\Domain\ValueObjects\AppointmentTime;
use App\Modules\Booking\Domain\ValueObjects\BookingId;
use App\Modules\Booking\Domain\ValueObjects\BookingReference;
use App\Modules\Booking\Domain\ValueObjects\BookingStatus;
use App\Modules\Booking\Domain\ValueObjects\PatientEmail;
use App\Modules\Booking\Domain\ValueObjects\PatientName;
use App\Modules\Booking\Domain\ValueObjects\PatientPhone;
use App\Modules\Booking\Domain\ValueObjects\ScheduledAppointment;
use App\Modules\Booking\Domain\ValueObjects\ServiceId;
use App\Modules\Booking\Domain\ValueObjects\TenantId;
use DateTimeImmutable;

final class Booking
{
    public function __construct(
        public readonly BookingId $id,
        public readonly TenantId $tenantId,
        public readonly ?ServiceId $serviceId,
        public readonly BookingReference $reference,
        private BookingStatus $status,
        public PatientName $patientName,
        public PatientPhone $patientPhone,
        public ?PatientEmail $patientEmail,
        public AppointmentDate $appointmentDate,
        public AppointmentTime $appointmentTime,
        public ?string $notes,
        public readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private int $version = 0,
        private ?ScheduledAppointment $scheduledAppointment = null,
    ) {}

    public static function submit(
        BookingId $id,
        TenantId $tenantId,
        ?ServiceId $serviceId,
        BookingReference $reference,
        PatientName $patientName,
        PatientPhone $patientPhone,
        ?PatientEmail $patientEmail,
        AppointmentDate $appointmentDate,
        AppointmentTime $appointmentTime,
        ?string $notes,
        DateTimeImmutable $occurredAt,
        ?ScheduledAppointment $scheduledAppointment = null,
    ): self {
        return new self(
            id: $id,
            tenantId: $tenantId,
            serviceId: $serviceId,
            reference: $reference,
            status: BookingStatus::Submitted,
            patientName: $patientName,
            patientPhone: $patientPhone,
            patientEmail: $patientEmail,
            appointmentDate: $appointmentDate,
            appointmentTime: $appointmentTime,
            notes: $notes,
            createdAt: $occurredAt,
            updatedAt: $occurredAt,
            version: 0,
            scheduledAppointment: $scheduledAppointment,
        );
    }

    public function status(): BookingStatus
    {
        return $this->status;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function scheduledAppointment(): ScheduledAppointment
    {
        if ($this->scheduledAppointment === null) {
            throw new InvalidBookingValueException('Booking has no scheduling snapshot.');
        }

        return $this->scheduledAppointment;
    }

    public function confirm(DateTimeImmutable $occurredAt): void
    {
        if ($this->status !== BookingStatus::Submitted) {
            throw new InvalidBookingValueException('Only a submitted Booking may be confirmed.');
        }
        $this->status = BookingStatus::Confirmed;
        $this->updatedAt = $occurredAt;
    }

    public function reschedule(ScheduledAppointment $appointment, DateTimeImmutable $occurredAt): ScheduledAppointment
    {
        if (! in_array($this->status, [BookingStatus::Submitted, BookingStatus::Confirmed], true)) {
            throw new InvalidBookingValueException('Only a submitted or confirmed Booking may be rescheduled.');
        }
        $previous = $this->scheduledAppointment();
        $this->scheduledAppointment = $appointment;
        $this->appointmentDate = $appointment->localDate;
        $this->appointmentTime = $appointment->localStart;
        $this->updatedAt = $occurredAt;

        return $previous;
    }

    public function cancel(DateTimeImmutable $occurredAt): void
    {
        if (! in_array($this->status, [BookingStatus::Submitted, BookingStatus::Confirmed], true)) {
            throw new InvalidBookingValueException('Only a submitted or confirmed Booking may be cancelled.');
        }
        $this->status = BookingStatus::Cancelled;
        $this->updatedAt = $occurredAt;
    }

    public function synchronizeVersion(int $version): void
    {
        $this->version = $version;
    }
}
