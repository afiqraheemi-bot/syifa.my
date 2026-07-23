<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Delivery;

use App\Modules\Booking\Application\Commands\SubmitBookingCommand as BookingSubmitBookingCommand;
use App\Modules\Booking\Application\Exceptions\BookingFormConfigurationNotFoundException;
use App\Modules\Booking\Application\Exceptions\BookingServiceInactiveException;
use App\Modules\Booking\Application\Exceptions\BookingServiceNotFoundException;
use App\Modules\Booking\Application\Exceptions\BookingSubmissionFailedException;
use App\Modules\Booking\Application\Exceptions\DisabledBookingFieldSuppliedException;
use App\Modules\Booking\Application\Exceptions\InvalidClinicBookingConfigurationException;
use App\Modules\Booking\Application\Exceptions\RequiredBookingFieldMissingException;
use App\Modules\Booking\Application\Exceptions\SlotUnavailableException;
use App\Modules\Booking\Application\SubmitBookingService;
use App\Modules\Booking\Contracts\ClinicOperationalTime\ClinicOperationalTimeNotFoundException;
use App\Modules\Booking\Domain\Exceptions\InvalidBookingValueException;
use App\Modules\WebsiteBuilder\Contracts\Delivery\BookingSubmissionGatewayInterface;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingAvailabilityException;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingBusinessRuleException;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingInfrastructureException;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingSubmission;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingSubmissionResult;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingValidationException;

/**
 * The real `BookingSubmissionGatewayInterface` implementation (ADR-029/ADR-030),
 * calling the real Booking Engine's `SubmitBookingService`. The internal Booking
 * exception thrown always names an implementation detail, never public-safe
 * copy — this adapter discards every internal message and substitutes the fixed,
 * ADR-027/UI-Specification-approved microcopy for the category it maps to, so no
 * internal detail is ever exposed to a public visitor (ADR-013 Public Exposure
 * Guardrail). The success result is deliberately re-built rather than passed
 * through, so a Booking ID can never leak into the public response shape.
 */
final readonly class BookingSubmissionGatewayAdapter implements BookingSubmissionGatewayInterface
{
    public function __construct(private SubmitBookingService $submissions) {}

    public function submit(PublicBookingSubmission $submission): PublicBookingSubmissionResult
    {
        try {
            $result = $this->submissions->execute(new BookingSubmitBookingCommand(
                $submission->tenantId, $submission->patientName, $submission->phone,
                $submission->appointmentDate, $submission->appointmentTime, $submission->consent,
                $submission->serviceId, $submission->email, $submission->notes,
            ));
        } catch (RequiredBookingFieldMissingException|DisabledBookingFieldSuppliedException|InvalidBookingValueException $exception) {
            throw new PublicBookingValidationException('Enter a phone number we can reach you on.', 0, $exception);
        } catch (BookingFormConfigurationNotFoundException|BookingServiceNotFoundException|BookingServiceInactiveException|InvalidClinicBookingConfigurationException|ClinicOperationalTimeNotFoundException $exception) {
            throw new PublicBookingBusinessRuleException("This option isn't available right now. Please choose another.", 0, $exception);
        } catch (SlotUnavailableException $exception) {
            throw new PublicBookingAvailabilityException('That time was just taken. Please choose another.', 0, $exception);
        } catch (BookingSubmissionFailedException $exception) {
            throw new PublicBookingInfrastructureException('Something went wrong on our end. Please try again.', 0, $exception);
        }

        return new PublicBookingSubmissionResult($result->reference, $result->status, $result->createdAt);
    }
}
