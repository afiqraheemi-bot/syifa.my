<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application;

use App\Modules\Booking\Application\Commands\CreateBookingCommand;
use App\Modules\Booking\Application\Commands\SubmitBookingCommand;
use App\Modules\Booking\Application\Exceptions\BookingFormConfigurationNotFoundException;
use App\Modules\Booking\Application\Exceptions\BookingServiceInactiveException;
use App\Modules\Booking\Application\Exceptions\BookingServiceNotFoundException;
use App\Modules\Booking\Application\Exceptions\BookingSubmissionFailedException;
use App\Modules\Booking\Application\Exceptions\DisabledBookingFieldSuppliedException;
use App\Modules\Booking\Application\Exceptions\InvalidClinicBookingConfigurationException;
use App\Modules\Booking\Application\Exceptions\RequiredBookingFieldMissingException;
use App\Modules\Booking\Application\Exceptions\SlotUnavailableException;
use App\Modules\Booking\Application\Results\BookingSubmissionResult;
use App\Modules\Booking\Contracts\ClinicOperationalTime\ClinicOperationalTimeNotFoundException;
use App\Modules\Booking\Domain\Exceptions\InvalidBookingValueException;
use App\Modules\Booking\Domain\ValueObjects\BookingActorType;
use App\Modules\Booking\Domain\ValueObjects\BookingSource;
use Throwable;

final readonly class SubmitBookingService
{
    public function __construct(private CreateBookingWorkflow $workflow) {}

    public function execute(SubmitBookingCommand $command): BookingSubmissionResult
    {
        try {
            return $this->workflow->execute(new CreateBookingCommand(
                $command->tenantId, BookingSource::Website, BookingActorType::PublicVisitor, null,
                $command->patientName, $command->phone, $command->appointmentDate, $command->appointmentTime,
                $command->serviceId, $command->email, $command->notes, $command->consent,
            ));
        } catch (BookingFormConfigurationNotFoundException|RequiredBookingFieldMissingException|DisabledBookingFieldSuppliedException|BookingServiceNotFoundException|BookingServiceInactiveException|InvalidBookingValueException|InvalidClinicBookingConfigurationException|ClinicOperationalTimeNotFoundException|SlotUnavailableException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new BookingSubmissionFailedException('Booking submission could not be completed.', 0, $exception);
        }
    }
}
