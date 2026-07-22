<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application;

use App\Modules\Booking\Application\Commands\SubmitBookingCommand;
use App\Modules\Booking\Application\Exceptions\BookingFormConfigurationNotFoundException;
use App\Modules\Booking\Application\Exceptions\BookingServiceInactiveException;
use App\Modules\Booking\Application\Exceptions\BookingServiceNotFoundException;
use App\Modules\Booking\Application\Exceptions\BookingSubmissionFailedException;
use App\Modules\Booking\Application\Exceptions\DisabledBookingFieldSuppliedException;
use App\Modules\Booking\Application\Exceptions\RequiredBookingFieldMissingException;
use App\Modules\Booking\Application\Results\BookingSubmissionResult;
use App\Modules\Booking\Contracts\Clock\BookingClockInterface;
use App\Modules\Booking\Contracts\Repositories\BookingFormConfigurationRepositoryInterface;
use App\Modules\Booking\Contracts\Repositories\BookingRepositoryInterface;
use App\Modules\Booking\Contracts\Repositories\ServiceRepositoryInterface;
use App\Modules\Booking\Contracts\Transactions\BookingTransactionInterface;
use App\Modules\Booking\Domain\Booking;
use App\Modules\Booking\Domain\BookingFormConfiguration;
use App\Modules\Booking\Domain\Exceptions\InvalidBookingValueException;
use App\Modules\Booking\Domain\ValueObjects\AppointmentDate;
use App\Modules\Booking\Domain\ValueObjects\AppointmentTime;
use App\Modules\Booking\Domain\ValueObjects\BookingFormField;
use App\Modules\Booking\Domain\ValueObjects\BookingId;
use App\Modules\Booking\Domain\ValueObjects\BookingReference;
use App\Modules\Booking\Domain\ValueObjects\PatientEmail;
use App\Modules\Booking\Domain\ValueObjects\PatientName;
use App\Modules\Booking\Domain\ValueObjects\PatientPhone;
use App\Modules\Booking\Domain\ValueObjects\ServiceId;
use App\Modules\Booking\Domain\ValueObjects\ServiceStatus;
use Throwable;

final readonly class SubmitBookingService
{
    public function __construct(
        private BookingFormConfigurationRepositoryInterface $configurations,
        private ServiceRepositoryInterface $services,
        private BookingRepositoryInterface $bookings,
        private BookingTransactionInterface $transactions,
        private BookingClockInterface $clock,
        private BookingIdentifierGeneratorInterface $identifiers,
        private BookingReferenceGeneratorInterface $references,
    ) {}

    public function execute(SubmitBookingCommand $command): BookingSubmissionResult
    {
        try {
            return $this->transactions->run(function () use ($command): BookingSubmissionResult {
                $configuration = $this->configurations->findByTenant($command->tenantId);

                if ($configuration === null) {
                    throw new BookingFormConfigurationNotFoundException('Booking Form Configuration was not found for the requested Tenant.');
                }

                $serviceId = $this->validatedServiceId($command, $configuration);
                $this->validateOptionalField($configuration, BookingFormField::Email, $command->email);
                $this->validateOptionalField($configuration, BookingFormField::Notes, $command->notes);
                $occurredAt = $this->clock->now();

                $booking = Booking::submit(
                    new BookingId($this->identifiers->generate()),
                    $command->tenantId,
                    $serviceId,
                    new BookingReference($this->references->generate()),
                    new PatientName($command->patientName),
                    new PatientPhone($command->phone),
                    $command->email === null ? null : new PatientEmail($command->email),
                    new AppointmentDate($command->appointmentDate),
                    new AppointmentTime($command->appointmentTime),
                    $command->notes,
                    $occurredAt,
                );

                $this->bookings->save($booking);

                return new BookingSubmissionResult(
                    $booking->id->value,
                    $booking->reference->value,
                    $booking->status()->value,
                    $booking->createdAt,
                );
            });
        } catch (BookingFormConfigurationNotFoundException|RequiredBookingFieldMissingException|DisabledBookingFieldSuppliedException|BookingServiceNotFoundException|BookingServiceInactiveException|InvalidBookingValueException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new BookingSubmissionFailedException('Booking submission could not be completed.', 0, $exception);
        }
    }

    private function validatedServiceId(
        SubmitBookingCommand $command,
        BookingFormConfiguration $configuration,
    ): ?ServiceId {
        $this->validateOptionalField($configuration, BookingFormField::Service, $command->serviceId);

        if ($command->serviceId === null) {
            return null;
        }

        $serviceId = new ServiceId($command->serviceId);
        $service = $this->services->findById($command->tenantId, $serviceId);

        if ($service === null) {
            throw new BookingServiceNotFoundException('The requested Service is unavailable.');
        }

        if ($service->status() !== ServiceStatus::Active) {
            throw new BookingServiceInactiveException('The requested Service is inactive.');
        }

        return $serviceId;
    }

    private function validateOptionalField(
        BookingFormConfiguration $configuration,
        BookingFormField $field,
        ?string $value,
    ): void {
        if (! $configuration->isEnabled($field)) {
            if ($value !== null) {
                throw new DisabledBookingFieldSuppliedException(
                    sprintf('The disabled field "%s" must not be supplied.', $field->value),
                );
            }

            return;
        }

        if ($configuration->requiredFields()->contains($field) && ($value === null || trim($value) === '')) {
            throw new RequiredBookingFieldMissingException(
                sprintf('The required field "%s" must be supplied.', $field->value),
            );
        }
    }
}
