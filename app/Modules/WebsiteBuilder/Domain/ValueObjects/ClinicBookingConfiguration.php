<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\ValueObjects;

final readonly class ClinicBookingConfiguration
{
    public function __construct(
        public BookingAppointmentDuration $appointmentDuration,
        public BookingCapacityPerSlot $capacityPerSlot,
    ) {}
}
