<?php

declare(strict_types=1);

namespace App\Modules\Booking\Contracts\Operations;

interface ClinicOwnerBookingOperationsInterface
{
    public function confirm(string $tenantId, string $bookingId, string $actorId, string $actorRole): void;

    public function cancel(string $tenantId, string $bookingId, string $actorId, string $actorRole): void;

    public function reschedule(
        string $tenantId,
        string $bookingId,
        string $localDate,
        string $localStart,
        string $actorId,
        string $actorRole,
    ): void;
}
