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
use App\Modules\Notification\Contracts\TransactionalNotificationGatewayInterface;

final readonly class ConfirmBookingService
{
    public function __construct(
        private BookingRepositoryInterface $bookings,
        private BookingHistoryRepositoryInterface $history,
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
            $at = $this->clock->now();
            $booking->confirm($at);
            $this->bookings->save($booking);
            $this->history->append(BookingHistoryEntry::confirmed($this->identifiers->generate(), $booking, $command->actorId, $at));
            $notification = [$booking->reference->value, $booking->patientEmail?->value];
        });
        $this->notify($command, $notification, 'confirmed');
    }

    /** @param null|array{string, ?string} $data */
    private function notify(BookingOwnerCommand $command, ?array $data, string $change): void
    {
        if ($data !== null) {
            $this->notifications?->bookingChanged($command->tenantId->value, $command->bookingId->value, $data[0], $data[1], $change);
        }
    }
}
