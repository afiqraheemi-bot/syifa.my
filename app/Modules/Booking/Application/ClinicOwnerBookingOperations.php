<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application;

use App\Modules\Booking\Application\Commands\BookingOwnerCommand;
use App\Modules\Booking\Application\Commands\RescheduleBookingCommand;
use App\Modules\Booking\Contracts\Operations\ClinicOwnerBookingOperationsInterface;
use App\Modules\Booking\Domain\ValueObjects\BookingId;
use App\Modules\Booking\Domain\ValueObjects\TenantId;

final readonly class ClinicOwnerBookingOperations implements ClinicOwnerBookingOperationsInterface
{
    public function __construct(
        private ConfirmBookingService $confirm,
        private CancelBookingService $cancel,
        private RescheduleBookingService $reschedule,
    ) {}

    public function confirm(string $tenantId, string $bookingId, string $actorId, string $actorRole): void
    {
        $this->confirm->execute($this->ownerCommand($tenantId, $bookingId, $actorId, $actorRole));
    }

    public function cancel(string $tenantId, string $bookingId, string $actorId, string $actorRole): void
    {
        $this->cancel->execute($this->ownerCommand($tenantId, $bookingId, $actorId, $actorRole));
    }

    public function reschedule(
        string $tenantId,
        string $bookingId,
        string $localDate,
        string $localStart,
        string $actorId,
        string $actorRole,
    ): void {
        $this->reschedule->execute(new RescheduleBookingCommand(
            new TenantId($tenantId),
            new BookingId($bookingId),
            $localDate,
            $localStart,
            $actorId,
            $actorRole,
        ));
    }

    private function ownerCommand(
        string $tenantId,
        string $bookingId,
        string $actorId,
        string $actorRole,
    ): BookingOwnerCommand {
        return new BookingOwnerCommand(
            new TenantId($tenantId),
            new BookingId($bookingId),
            $actorId,
            $actorRole,
        );
    }
}
