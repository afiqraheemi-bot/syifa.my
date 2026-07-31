<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application;

use App\Modules\Booking\Application\Commands\BookingOwnerCommand;
use App\Modules\Booking\Application\Exceptions\BookingOperationNotFoundException;
use App\Modules\Booking\Contracts\Clock\BookingClockInterface;
use App\Modules\Booking\Contracts\Repositories\BookingHistoryRepositoryInterface;
use App\Modules\Booking\Contracts\Repositories\BookingRepositoryInterface;
use App\Modules\Booking\Contracts\Transactions\BookingTransactionInterface;
use App\Modules\Booking\Domain\BookingHistoryEntry;

final readonly class CompleteBookingService
{
    public function __construct(
        private BookingRepositoryInterface $bookings,
        private BookingHistoryRepositoryInterface $history,
        private BookingTransactionInterface $transactions,
        private BookingClockInterface $clock,
        private BookingHistoryIdentifierGeneratorInterface $identifiers,
        private BookingOwnerAuthorization $authorization,
    ) {}

    public function execute(BookingOwnerCommand $command): void
    {
        $this->authorization->assertClinicOwner($command->actorId, $command->actorRole);
        $this->transactions->run(function () use ($command): void {
            $booking = $this->bookings->findById($command->tenantId, $command->bookingId)
                ?? throw new BookingOperationNotFoundException('Booking was not found.');
            $at = $this->clock->now();
            $booking->complete($at);
            $this->bookings->save($booking);
            $this->history->append(BookingHistoryEntry::completed(
                $this->identifiers->generate(),
                $booking,
                $command->actorId,
                $at,
            ));
        });
    }
}
