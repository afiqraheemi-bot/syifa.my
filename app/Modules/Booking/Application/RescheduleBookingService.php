<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application;

use App\Modules\Booking\Application\Availability\ClinicSlotGenerator;
use App\Modules\Booking\Application\Commands\RescheduleBookingCommand;
use App\Modules\Booking\Application\Exceptions\BookingOperationNotFoundException;
use App\Modules\Booking\Contracts\Capacity\ReservationSlotData;
use App\Modules\Booking\Contracts\Capacity\SlotCapacityReservationInterface;
use App\Modules\Booking\Contracts\ClinicOperationalTime\ClinicOperationalTimeReaderInterface;
use App\Modules\Booking\Contracts\Clock\BookingClockInterface;
use App\Modules\Booking\Contracts\Repositories\BookingHistoryRepositoryInterface;
use App\Modules\Booking\Contracts\Repositories\BookingRepositoryInterface;
use App\Modules\Booking\Contracts\Transactions\BookingTransactionInterface;
use App\Modules\Booking\Domain\BookingHistoryEntry;
use App\Modules\Booking\Domain\ScheduledAppointmentPair;
use App\Modules\Booking\Domain\ValueObjects\AppointmentDate;
use App\Modules\Booking\Domain\ValueObjects\AppointmentTime;
use App\Modules\Booking\Domain\ValueObjects\ScheduledAppointment;

final readonly class RescheduleBookingService
{
    public function __construct(
        private BookingRepositoryInterface $bookings,
        private BookingHistoryRepositoryInterface $history,
        private SlotCapacityReservationInterface $capacity,
        private ClinicOperationalTimeReaderInterface $clinic,
        private ClinicSlotGenerator $slots,
        private BookingTransactionInterface $transactions,
        private BookingClockInterface $clock,
        private BookingHistoryIdentifierGeneratorInterface $identifiers,
        private BookingOwnerAuthorization $authorization,
    ) {}

    public function execute(RescheduleBookingCommand $command): void
    {
        $this->authorization->assertClinicOwner($command->actorId, $command->actorRole);
        $this->transactions->run(function () use ($command): void {
            $booking = $this->bookings->findById($command->tenantId, $command->bookingId)
                ?? throw new BookingOperationNotFoundException('Booking was not found.');
            $clinic = $this->clinic->forTrustedTenant($command->tenantId->value);
            $slot = $this->slots->exact($clinic, $command->localDate, $command->localStart);
            $new = new ScheduledAppointment(new AppointmentDate($slot->localDate), new AppointmentTime($slot->localStart), new AppointmentTime($slot->localEnd), $slot->timezone, $slot->startsAtUtc, $slot->endsAtUtc, $slot->durationMinutes);
            $target = new ReservationSlotData($new->startsAtUtc, $new->endsAtUtc);
            $this->capacity->reserve($command->tenantId->value, $target, (int) $clinic->bookingCapacityPerSlot);
            $at = $this->clock->now();
            $old = $booking->reschedule($new, $at);
            $this->bookings->save($booking);
            $this->history->append(BookingHistoryEntry::rescheduled($this->identifiers->generate(), $booking, new ScheduledAppointmentPair($old, $new), $command->actorId, $command->note, $at));
            $this->capacity->release($command->tenantId->value, new ReservationSlotData($old->startsAtUtc, $old->endsAtUtc));
        });
    }
}
