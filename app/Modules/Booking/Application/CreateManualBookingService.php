<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application;

use App\Modules\Booking\Application\Commands\CreateBookingCommand;
use App\Modules\Booking\Application\Commands\CreateManualBookingCommand;
use App\Modules\Booking\Application\Exceptions\BookingFormConfigurationNotFoundException;
use App\Modules\Booking\Application\Exceptions\BookingServiceInactiveException;
use App\Modules\Booking\Application\Exceptions\BookingServiceNotFoundException;
use App\Modules\Booking\Application\Exceptions\DisabledBookingFieldSuppliedException;
use App\Modules\Booking\Application\Exceptions\InvalidClinicBookingConfigurationException;
use App\Modules\Booking\Application\Exceptions\ManualBookingCreationFailedException;
use App\Modules\Booking\Application\Exceptions\RequiredBookingFieldMissingException;
use App\Modules\Booking\Application\Exceptions\SlotUnavailableException;
use App\Modules\Booking\Application\Exceptions\UnsupportedManualBookingSourceException;
use App\Modules\Booking\Application\Results\BookingSubmissionResult;
use App\Modules\Booking\Contracts\ClinicOperationalTime\ClinicOperationalTimeNotFoundException;
use App\Modules\Booking\Domain\Exceptions\InvalidBookingValueException;
use App\Modules\Booking\Domain\ValueObjects\BookingActorType;
use App\Modules\Booking\Domain\ValueObjects\BookingSource;
use Throwable;

final readonly class CreateManualBookingService
{
    public function __construct(private CreateBookingWorkflow $workflow, private BookingOwnerAuthorization $authorization) {}

    public function execute(CreateManualBookingCommand $command): BookingSubmissionResult
    {
        $this->authorization->assertClinicOwnerForTenant($command->actorId, $command->actorRole, $command->actorTenantId, $command->tenantId);
        $source = BookingSource::tryFrom($command->source);
        if ($source === null || ! $source->isManual()) {
            throw new UnsupportedManualBookingSourceException('The requested manual Booking source is unsupported.');
        }

        try {
            return $this->workflow->execute(new CreateBookingCommand(
                $command->tenantId, $source, BookingActorType::ClinicOwner, $command->actorId,
                $command->patientName, $command->phone, $command->appointmentDate, $command->appointmentTime,
                $command->serviceId, $command->email, $command->notes,
            ));
        } catch (BookingFormConfigurationNotFoundException|RequiredBookingFieldMissingException|DisabledBookingFieldSuppliedException|BookingServiceNotFoundException|BookingServiceInactiveException|InvalidBookingValueException|InvalidClinicBookingConfigurationException|ClinicOperationalTimeNotFoundException|SlotUnavailableException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ManualBookingCreationFailedException('Manual Booking creation could not be completed.', 0, $exception);
        }
    }
}
