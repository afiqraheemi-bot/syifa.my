<?php

declare(strict_types=1);

namespace App\Modules\Booking\Contracts\Capacity;

interface SlotCapacityReservationInterface
{
    public function reserve(string $tenantId, ReservationSlotData $slot, int $capacity): void;

    public function release(string $tenantId, ReservationSlotData $slot): void;

    public function isAvailable(string $tenantId, ReservationSlotData $slot): bool;
}
