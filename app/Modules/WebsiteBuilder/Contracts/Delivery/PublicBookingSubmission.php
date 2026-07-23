<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\Delivery;

/**
 * The canonical Public Booking Contract request (ADR-027, consent added by ADR-030).
 * `tenantId` is always Delivery-resolved via a trusted context, never visitor-supplied.
 */
final readonly class PublicBookingSubmission
{
    public function __construct(
        public string $tenantId,
        public string $patientName,
        public string $phone,
        public string $appointmentDate,
        public string $appointmentTime,
        public bool $consent,
        public ?string $serviceId = null,
        public ?string $email = null,
        public ?string $notes = null,
    ) {
        if (trim($tenantId) === '') {
            throw new PublicBookingValidationException('Tenant identity must not be blank.');
        }
        if (trim($patientName) === '') {
            throw new PublicBookingValidationException('Patient name must not be blank.');
        }
        if (trim($phone) === '') {
            throw new PublicBookingValidationException('Patient phone must not be blank.');
        }
        if (trim($appointmentDate) === '') {
            throw new PublicBookingValidationException('Appointment date must not be blank.');
        }
        if (trim($appointmentTime) === '') {
            throw new PublicBookingValidationException('Appointment time must not be blank.');
        }
    }
}
