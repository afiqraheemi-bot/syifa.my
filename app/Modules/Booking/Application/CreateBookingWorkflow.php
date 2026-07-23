<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application;

use App\Modules\Booking\Application\Availability\ClinicSlotGenerator;
use App\Modules\Booking\Application\Commands\CreateBookingCommand;
use App\Modules\Booking\Application\Exceptions\BookingFormConfigurationNotFoundException;
use App\Modules\Booking\Application\Exceptions\BookingServiceInactiveException;
use App\Modules\Booking\Application\Exceptions\BookingServiceNotFoundException;
use App\Modules\Booking\Application\Exceptions\DisabledBookingFieldSuppliedException;
use App\Modules\Booking\Application\Exceptions\RequiredBookingFieldMissingException;
use App\Modules\Booking\Application\Results\BookingSubmissionResult;
use App\Modules\Booking\Contracts\Capacity\ReservationSlotData;
use App\Modules\Booking\Contracts\Capacity\SlotCapacityReservationInterface;
use App\Modules\Booking\Contracts\ClinicOperationalTime\ClinicOperationalTimeReaderInterface;
use App\Modules\Booking\Contracts\Clock\BookingClockInterface;
use App\Modules\Booking\Contracts\Repositories\BookingFormConfigurationRepositoryInterface;
use App\Modules\Booking\Contracts\Repositories\BookingHistoryRepositoryInterface;
use App\Modules\Booking\Contracts\Repositories\BookingRepositoryInterface;
use App\Modules\Booking\Contracts\Repositories\ServiceRepositoryInterface;
use App\Modules\Booking\Contracts\Transactions\BookingTransactionInterface;
use App\Modules\Booking\Domain\Booking;
use App\Modules\Booking\Domain\BookingFormConfiguration;
use App\Modules\Booking\Domain\BookingHistoryEntry;
use App\Modules\Booking\Domain\ValueObjects\AppointmentDate;
use App\Modules\Booking\Domain\ValueObjects\AppointmentTime;
use App\Modules\Booking\Domain\ValueObjects\BookingFormField;
use App\Modules\Booking\Domain\ValueObjects\BookingId;
use App\Modules\Booking\Domain\ValueObjects\BookingReference;
use App\Modules\Booking\Domain\ValueObjects\BookingSource;
use App\Modules\Booking\Domain\ValueObjects\PatientEmail;
use App\Modules\Booking\Domain\ValueObjects\PatientName;
use App\Modules\Booking\Domain\ValueObjects\PatientPhone;
use App\Modules\Booking\Domain\ValueObjects\ScheduledAppointment;
use App\Modules\Booking\Domain\ValueObjects\ServiceId;
use App\Modules\Booking\Domain\ValueObjects\ServiceStatus;
use App\Modules\Booking\Domain\ValueObjects\TenantId;

final readonly class CreateBookingWorkflow
{
    public function __construct(
        private BookingFormConfigurationRepositoryInterface $configurations,
        private ServiceRepositoryInterface $services,
        private BookingRepositoryInterface $bookings,
        private BookingTransactionInterface $transactions,
        private BookingClockInterface $clock,
        private BookingIdentifierGeneratorInterface $identifiers,
        private BookingReferenceGeneratorInterface $references,
        private ClinicOperationalTimeReaderInterface $clinicOperationalTime,
        private ClinicSlotGenerator $slots,
        private SlotCapacityReservationInterface $capacity,
        private BookingHistoryRepositoryInterface $history,
        private BookingHistoryIdentifierGeneratorInterface $historyIdentifiers,
    ) {}

    public function execute(CreateBookingCommand $command): BookingSubmissionResult
    {
        return $this->transactions->run(function () use ($command): BookingSubmissionResult {
            if ($command->source === BookingSource::Website && $command->consent !== true) {
                throw new RequiredBookingFieldMissingException('The required field "consent" must be supplied.');
            }
            $tenantId = new TenantId($command->tenantId);
            $configuration = $this->configurations->findByTenant($tenantId)
                ?? throw new BookingFormConfigurationNotFoundException('Booking Form Configuration was not found for the requested Tenant.');
            $serviceId = $this->validatedServiceId($command, $tenantId, $configuration);
            $this->validateOptionalField($configuration, BookingFormField::Email, $command->email);
            $this->validateOptionalField($configuration, BookingFormField::Notes, $command->notes);
            $occurredAt = $this->clock->now();
            $clinic = $this->clinicOperationalTime->forTrustedTenant($command->tenantId);
            $slot = $this->slots->exact($clinic, $command->appointmentDate, $command->appointmentTime);
            $scheduled = new ScheduledAppointment(new AppointmentDate($slot->localDate), new AppointmentTime($slot->localStart), new AppointmentTime($slot->localEnd), $slot->timezone, $slot->startsAtUtc, $slot->endsAtUtc, $slot->durationMinutes);
            $this->capacity->reserve($command->tenantId, new ReservationSlotData($slot->startsAtUtc, $slot->endsAtUtc), (int) $clinic->bookingCapacityPerSlot);
            $booking = Booking::submit(new BookingId($this->identifiers->generate()), $tenantId, $serviceId, new BookingReference($this->references->generate()), $command->source, new PatientName($command->patientName), new PatientPhone($command->phone), $command->email === null ? null : new PatientEmail($command->email), new AppointmentDate($command->appointmentDate), new AppointmentTime($command->appointmentTime), $command->notes, $occurredAt, $scheduled);
            $this->bookings->save($booking);
            $this->history->append(BookingHistoryEntry::submitted($this->historyIdentifiers->generate(), $booking, $command->actorType, $command->actorId, $occurredAt, $command->consent));

            return new BookingSubmissionResult($booking->id->value, $booking->reference->value, $booking->status()->value, $booking->createdAt);
        });
    }

    private function validatedServiceId(CreateBookingCommand $command, TenantId $tenantId, BookingFormConfiguration $configuration): ServiceId
    {
        $this->validateOptionalField($configuration, BookingFormField::Service, $command->serviceId);
        if ($command->serviceId === null || trim($command->serviceId) === '') {
            throw new RequiredBookingFieldMissingException('The required field "service" must be supplied.');
        }
        $serviceId = new ServiceId($command->serviceId);
        $service = $this->services->findById($tenantId, $serviceId);
        if ($service === null) {
            throw new BookingServiceNotFoundException('The requested Service is unavailable.');
        }
        if ($service->status() !== ServiceStatus::Active) {
            throw new BookingServiceInactiveException('The requested Service is inactive.');
        }

        return $serviceId;
    }

    private function validateOptionalField(BookingFormConfiguration $configuration, BookingFormField $field, ?string $value): void
    {
        if (! $configuration->isEnabled($field)) {
            if ($value !== null) {
                throw new DisabledBookingFieldSuppliedException(sprintf('The disabled field "%s" must not be supplied.', $field->value));
            }

            return;
        }
        if ($configuration->requiredFields()->contains($field) && ($value === null || trim($value) === '')) {
            throw new RequiredBookingFieldMissingException(sprintf('The required field "%s" must be supplied.', $field->value));
        }
    }
}
