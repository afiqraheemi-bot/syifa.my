<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Persistence\Mappers;

use App\Modules\Booking\Domain\Booking;
use App\Modules\Booking\Domain\Exceptions\InvalidBookingValueException;
use App\Modules\Booking\Domain\ValueObjects\AppointmentDate;
use App\Modules\Booking\Domain\ValueObjects\AppointmentTime;
use App\Modules\Booking\Domain\ValueObjects\BookingId;
use App\Modules\Booking\Domain\ValueObjects\BookingReference;
use App\Modules\Booking\Domain\ValueObjects\BookingSource;
use App\Modules\Booking\Domain\ValueObjects\BookingStatus;
use App\Modules\Booking\Domain\ValueObjects\PatientEmail;
use App\Modules\Booking\Domain\ValueObjects\PatientName;
use App\Modules\Booking\Domain\ValueObjects\PatientPhone;
use App\Modules\Booking\Domain\ValueObjects\ScheduledAppointment;
use App\Modules\Booking\Domain\ValueObjects\ServiceId;
use App\Modules\Booking\Domain\ValueObjects\TenantId;
use App\Modules\Booking\Infrastructure\Persistence\Records\BookingStorageRecord;

final class BookingPersistenceMapper
{
    public function record(Booking $booking): BookingStorageRecord
    {
        $scheduled = null;
        try {
            $scheduled = $booking->scheduledAppointment();
        } catch (InvalidBookingValueException) {
        }

        return new BookingStorageRecord(
            $booking->id->value,
            $booking->tenantId->value,
            $booking->serviceId?->value,
            $booking->reference->value,
            $booking->source->value,
            $booking->status()->value,
            $booking->patientName->value,
            $booking->patientPhone->value,
            $booking->patientEmail?->value,
            $booking->appointmentDate->value,
            $booking->appointmentTime->value,
            $booking->notes,
            $booking->createdAt,
            $booking->updatedAt(),
            $booking->version(),
            $scheduled?->localEnd->value,
            $scheduled?->timezone,
            $scheduled?->startsAtUtc,
            $scheduled?->endsAtUtc,
            $scheduled?->durationMinutes,
        );
    }

    public function toDomain(BookingStorageRecord $record): Booking
    {
        $scheduled = null;
        if ($record->localEndTime !== null && $record->timezone !== null && $record->startsAtUtc !== null && $record->endsAtUtc !== null && $record->durationMinutes !== null) {
            $scheduled = new ScheduledAppointment(
                new AppointmentDate($record->appointmentOn),
                new AppointmentTime($record->appointmentTime),
                new AppointmentTime($record->localEndTime),
                $record->timezone,
                $record->startsAtUtc,
                $record->endsAtUtc,
                $record->durationMinutes,
            );
        }

        return new Booking(
            id: new BookingId($record->id),
            tenantId: new TenantId($record->tenantId),
            serviceId: $record->serviceId === null ? null : new ServiceId($record->serviceId),
            reference: new BookingReference($record->bookingReference),
            source: BookingSource::fromStored($record->bookingSource),
            status: BookingStatus::from($record->status),
            patientName: new PatientName($record->patientName),
            patientPhone: new PatientPhone($record->patientPhone),
            patientEmail: $record->patientEmail === null ? null : new PatientEmail($record->patientEmail),
            appointmentDate: new AppointmentDate($record->appointmentOn),
            appointmentTime: new AppointmentTime($record->appointmentTime),
            notes: $record->notes,
            createdAt: $record->domainCreatedAt,
            updatedAt: $record->domainUpdatedAt,
            version: $record->version,
            scheduledAppointment: $scheduled,
        );
    }
}
