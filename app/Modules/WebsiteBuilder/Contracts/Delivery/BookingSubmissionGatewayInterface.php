<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\Delivery;

/**
 * The sole permitted path from the Public Website Delivery layer into the Booking
 * Engine's submission entry point (ADR-027/ADR-029). Controllers never call this
 * interface directly — only the Booking Delivery Service does. Sprint 1 binds a
 * Stub implementation; a future Sprint binds a real adapter that calls
 * `SubmitBookingService`, translating its exception vocabulary into the four
 * categories named below (Security is rejected by Presentation middleware before
 * ever reaching this interface, per ADR-027's Error Contract).
 */
interface BookingSubmissionGatewayInterface
{
    /**
     * @throws PublicBookingValidationException required field missing or malformed
     * @throws PublicBookingBusinessRuleException service/configuration is not currently valid
     * @throws PublicBookingAvailabilityException the requested slot is no longer available
     * @throws PublicBookingInfrastructureException any other, unanticipated failure
     */
    public function submit(PublicBookingSubmission $submission): PublicBookingSubmissionResult;
}
