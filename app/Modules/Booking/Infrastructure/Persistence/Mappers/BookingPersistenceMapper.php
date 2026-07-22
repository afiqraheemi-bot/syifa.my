<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Persistence\Mappers;

use App\Modules\Booking\Domain\Booking;
use App\Modules\Booking\Domain\ValueObjects\AppointmentDate;
use App\Modules\Booking\Domain\ValueObjects\AppointmentTime;
use App\Modules\Booking\Domain\ValueObjects\BookingId;
use App\Modules\Booking\Domain\ValueObjects\BookingReference;
use App\Modules\Booking\Domain\ValueObjects\BookingStatus;
use App\Modules\Booking\Domain\ValueObjects\ClinicId;
use App\Modules\Booking\Domain\ValueObjects\PatientEmail;
use App\Modules\Booking\Domain\ValueObjects\PatientName;
use App\Modules\Booking\Domain\ValueObjects\PatientPhone;
use App\Modules\Booking\Domain\ValueObjects\ServiceId;
use App\Modules\Booking\Domain\ValueObjects\TenantId;
use App\Modules\Booking\Infrastructure\Persistence\Records\BookingStorageRecord;

final class BookingPersistenceMapper
{
    public function record(Booking $booking): BookingStorageRecord
    {
        return new BookingStorageRecord(
            $booking->id->value,
            $booking->tenantId->value,
            $booking->clinicId->value,
            $booking->serviceId?->value,
            $booking->reference->value,
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
        );
    }

    public function toDomain(BookingStorageRecord $record): Booking
    {
        return new Booking(
            id: new BookingId($record->id),
            tenantId: new TenantId($record->tenantId),
            clinicId: new ClinicId($record->clinicId),
            serviceId: $record->serviceId === null ? null : new ServiceId($record->serviceId),
            reference: new BookingReference($record->bookingReference),
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
        );
    }
}
