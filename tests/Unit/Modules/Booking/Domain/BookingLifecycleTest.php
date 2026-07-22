<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Booking\Domain;

use App\Modules\Booking\Domain\Booking;
use App\Modules\Booking\Domain\BookingHistoryEntry;
use App\Modules\Booking\Domain\Exceptions\InvalidBookingValueException;
use App\Modules\Booking\Domain\ValueObjects\AppointmentDate;
use App\Modules\Booking\Domain\ValueObjects\AppointmentTime;
use App\Modules\Booking\Domain\ValueObjects\BookingId;
use App\Modules\Booking\Domain\ValueObjects\BookingReference;
use App\Modules\Booking\Domain\ValueObjects\BookingStatus;
use App\Modules\Booking\Domain\ValueObjects\PatientName;
use App\Modules\Booking\Domain\ValueObjects\PatientPhone;
use App\Modules\Booking\Domain\ValueObjects\ScheduledAppointment;
use App\Modules\Booking\Domain\ValueObjects\ServiceId;
use App\Modules\Booking\Domain\ValueObjects\TenantId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class BookingLifecycleTest extends TestCase
{
    public function test_confirm_reschedule_and_cancel_preserve_approved_lifecycle(): void
    {
        $booking = $this->booking();
        $booking->confirm($this->at('+1 hour'));
        $old = $booking->reschedule($this->scheduled('11:00'), $this->at('+2 hours'));

        self::assertSame(BookingStatus::Confirmed, $booking->status());
        self::assertSame('10:00', $old->localStart->value);
        self::assertSame('11:00', $booking->scheduledAppointment()->localStart->value);

        $booking->cancel($this->at('+3 hours'));
        self::assertSame(BookingStatus::Cancelled, $booking->status());
    }

    public function test_repeated_cancel_is_rejected_before_capacity_can_be_released_again(): void
    {
        $booking = $this->booking();
        $booking->cancel($this->at('+1 hour'));

        $this->expectException(InvalidBookingValueException::class);
        $booking->cancel($this->at('+2 hours'));
    }

    public function test_history_reconstitution_accepts_jsonb_key_order_but_rejects_unknown_fields(): void
    {
        $entry = BookingHistoryEntry::submitted($this->uuid(9), $this->booking(), $this->at());
        $reordered = array_reverse($entry->payload, true);
        self::assertEquals($entry->payload, BookingHistoryEntry::reconstitute($entry->id, $entry->tenantId, $entry->bookingId, $entry->eventType->value, $entry->actorType->value, null, $entry->occurredAt, $reordered)->payload);

        $this->expectException(InvalidBookingValueException::class);
        BookingHistoryEntry::reconstitute($entry->id, $entry->tenantId, $entry->bookingId, $entry->eventType->value, $entry->actorType->value, null, $entry->occurredAt, [...$entry->payload, 'unknown' => 'value']);
    }

    private function booking(): Booking
    {
        return Booking::submit(new BookingId($this->uuid(1)), new TenantId($this->uuid(2)), new ServiceId($this->uuid(3)), new BookingReference('BOOK-1'), new PatientName('Patient'), new PatientPhone('+6012'), null, new AppointmentDate('2026-08-10'), new AppointmentTime('10:00'), null, $this->at(), $this->scheduled('10:00'));
    }

    private function scheduled(string $start): ScheduledAppointment
    {
        $utc = new DateTimeImmutable('2026-08-10 '.($start === '10:00' ? '02:00' : '03:00').':00Z');

        return new ScheduledAppointment(new AppointmentDate('2026-08-10'), new AppointmentTime($start), new AppointmentTime($start === '10:00' ? '10:30' : '11:30'), 'Asia/Kuala_Lumpur', $utc, $utc->modify('+30 minutes'), 30);
    }

    private function at(string $modify = ''): DateTimeImmutable
    {
        $at = new DateTimeImmutable('2026-08-01T00:00:00Z');

        return $modify === '' ? $at : $at->modify($modify);
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
