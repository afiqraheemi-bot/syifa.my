<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Commands;

use App\Modules\Booking\Domain\ValueObjects\BookingActorType;
use App\Modules\Booking\Domain\ValueObjects\BookingSource;

/**
 * `tenantId` is a plain, trusted string (ADR-030) — `CreateBookingWorkflow`
 * constructs the Domain `TenantId` internally, exactly once.
 *
 * `consent` is nullable: required and enforced (`true`) only for
 * `BookingSource::Website` submissions (ADR-027); not applicable for
 * manual sources (WhatsApp/Phone/Walk-in/Staff), which never collect it.
 */
final readonly class CreateBookingCommand
{
    public function __construct(
        public string $tenantId,
        public BookingSource $source,
        public BookingActorType $actorType,
        public ?string $actorId,
        public string $patientName,
        public string $phone,
        public string $appointmentDate,
        public string $appointmentTime,
        public ?string $serviceId,
        public ?string $email,
        public ?string $notes,
        public ?bool $consent = null,
    ) {}
}
