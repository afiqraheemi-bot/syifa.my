<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application;

use App\Modules\Booking\Application\Commands\BookingOwnerCommand;
use App\Modules\Booking\Application\Exceptions\BookingOperationNotFoundException;
use App\Modules\Booking\Contracts\Capacity\ReservationSlotData;
use App\Modules\Booking\Contracts\Capacity\SlotCapacityReservationInterface;
use App\Modules\Booking\Contracts\Clock\BookingClockInterface;
use App\Modules\Booking\Contracts\Repositories\BookingHistoryRepositoryInterface;
use App\Modules\Booking\Contracts\Repositories\BookingRepositoryInterface;
use App\Modules\Booking\Contracts\Transactions\BookingTransactionInterface;
use App\Modules\Booking\Domain\BookingHistoryEntry;
use App\Modules\Notification\Contracts\TransactionalNotificationGatewayInterface;

final readonly class CancelBookingService
{
    public function __construct(
        private BookingRepositoryInterface $bookings,
        private BookingHistoryRepositoryInterface $history,
        private SlotCapacityReservationInterface $capacity,
        private BookingTransactionInterface $transactions,
        private BookingClockInterface $clock,
        private BookingHistoryIdentifierGeneratorInterface $identifiers,
        private BookingOwnerAuthorization $authorization,
        private ?TransactionalNotificationGatewayInterface $notifications = null,
    ) {}

    public function execute(BookingOwnerCommand $command): void
    {
        $this->authorization->assertClinicOwner($command->actorId, $command->actorRole);
        $notification = null;
        $this->transactions->run(function () use ($command, &$notification): void {
            $booking = $this->bookings->findById($command->tenantId, $command->bookingId)
                ?? throw new BookingOperationNotFoundException('Booking was not found.');
            $scheduled = $booking->scheduledAppointment();
            $at = $this->clock->now();
            $booking->cancel($at);
            $this->capacity->release($command->tenantId->value, new ReservationSlotData($scheduled->startsAtUtc, $scheduled->endsAtUtc));
            $this->bookings->save($booking);
            $this->history->append(BookingHistoryEntry::cancelled($this->identifiers->generate(), $booking, $command->actorId, $command->reason, $at));
            $notification = [$booking->reference->value, $booking->patientEmail?->value];
        });
        if ($notification !== null) {
            $this->notifications?->bookingChanged($command->tenantId->value, $command->bookingId->value, $notification[0], $notification[1], 'cancelled');
        }
    }
}
