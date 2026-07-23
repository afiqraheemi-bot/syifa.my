<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Commands;

/**
 * The public Booking submission entry point (ADR-027). `tenantId` is a
 * plain, trusted string — never a Domain `TenantId` object — matching
 * every other Booking Contracts-facing interface
 * (`AvailableSlotReaderInterface`, `ClinicOperationalTimeReaderInterface`),
 * so no external caller ever needs to import a Booking Domain class
 * (ADR-030). `consent` is required: a public submission is the first point
 * an unaffiliated visitor's data is captured, and is recorded as part of
 * the submission for audit purposes alongside `BookingHistoryEntry`.
 */
final readonly class SubmitBookingCommand
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
    ) {}
}
