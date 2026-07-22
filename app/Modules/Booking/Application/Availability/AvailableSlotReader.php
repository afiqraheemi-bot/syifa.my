<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Availability;

use App\Modules\Booking\Contracts\Capacity\ReservationSlotData;
use App\Modules\Booking\Contracts\Capacity\SlotCapacityReservationInterface;
use App\Modules\Booking\Contracts\ClinicOperationalTime\ClinicOperationalTimeReaderInterface;
use App\Modules\Booking\Contracts\Queries\AvailableSlotData;
use App\Modules\Booking\Contracts\Queries\AvailableSlotReaderInterface;

final readonly class AvailableSlotReader implements AvailableSlotReaderInterface
{
    public function __construct(
        private ClinicOperationalTimeReaderInterface $clinic,
        private ClinicSlotGenerator $generator,
        private SlotCapacityReservationInterface $capacity,
    ) {}

    public function forDate(string $trustedTenantId, string $localDate): array
    {
        $slots = [];
        foreach ($this->generator->generate($this->clinic->forTrustedTenant($trustedTenantId), $localDate) as $slot) {
            $slots[] = new AvailableSlotData(
                $slot->localStart,
                $slot->localEnd,
                $slot->timezone,
                $this->capacity->isAvailable($trustedTenantId, new ReservationSlotData($slot->startsAtUtc, $slot->endsAtUtc)),
            );
        }

        return $slots;
    }
}
