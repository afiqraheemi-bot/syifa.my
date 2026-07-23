<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder\Infrastructure\Delivery;

use App\Modules\Booking\Application\Exceptions\InvalidClinicBookingConfigurationException;
use App\Modules\Booking\Contracts\ClinicOperationalTime\ClinicOperationalTimeNotFoundException;
use App\Modules\Booking\Contracts\Queries\AvailableSlotData;
use App\Modules\Booking\Contracts\Queries\AvailableSlotReaderInterface;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicAvailabilityState;
use App\Modules\WebsiteBuilder\Infrastructure\Delivery\BookingAvailableSlotReaderAdapter;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

final class BookingAvailableSlotReaderAdapterTest extends TestCase
{
    public function test_it_maps_available_and_unavailable_slots_directly(): void
    {
        $slots = $this->createStub(AvailableSlotReaderInterface::class);
        $slots->method('forDate')->willReturn([
            new AvailableSlotData('09:00', '09:30', 'Asia/Kuala_Lumpur', true),
            new AvailableSlotData('09:30', '10:00', 'Asia/Kuala_Lumpur', false),
        ]);

        $result = (new BookingAvailableSlotReaderAdapter($slots))->forDate('tenant-1', '2026-08-03');

        self::assertCount(2, $result);
        self::assertSame(PublicAvailabilityState::Available, $result[0]->state);
        self::assertSame(PublicAvailabilityState::Unavailable, $result[1]->state);
        self::assertSame('2026-08-03', $result[0]->localDate);
    }

    public function test_no_operational_time_configured_yields_an_empty_list_not_an_exception(): void
    {
        $slots = $this->createStub(AvailableSlotReaderInterface::class);
        $slots->method('forDate')->willThrowException(new ClinicOperationalTimeNotFoundException('none'));

        $result = (new BookingAvailableSlotReaderAdapter($slots))->forDate('tenant-1', '2026-08-03');

        self::assertSame([], $result);
    }

    public function test_incomplete_booking_configuration_yields_an_empty_list_not_an_exception(): void
    {
        $slots = $this->createStub(AvailableSlotReaderInterface::class);
        $slots->method('forDate')->willThrowException(new InvalidClinicBookingConfigurationException('incomplete'));

        $result = (new BookingAvailableSlotReaderAdapter($slots))->forDate('tenant-1', '2026-08-03');

        self::assertSame([], $result);
    }

    public function test_an_unexpected_failure_is_logged_and_represented_as_a_single_unknown_slot(): void
    {
        Log::shouldReceive('error')->once()->with('Public availability signal unobtainable.', self::callback(
            static fn (array $context): bool => $context['tenant_id'] === 'tenant-1' && $context['local_date'] === '2026-08-03',
        ));

        $slots = $this->createStub(AvailableSlotReaderInterface::class);
        $slots->method('forDate')->willThrowException(new RuntimeException('database is unreachable'));

        $result = (new BookingAvailableSlotReaderAdapter($slots))->forDate('tenant-1', '2026-08-03');

        self::assertCount(1, $result);
        self::assertSame(PublicAvailabilityState::Unknown, $result[0]->state);
    }

    public function test_an_unexpected_failure_never_leaks_the_underlying_exception_message(): void
    {
        Log::shouldReceive('error');

        $slots = $this->createStub(AvailableSlotReaderInterface::class);
        $slots->method('forDate')->willThrowException(new RuntimeException('super secret internal detail'));

        $result = (new BookingAvailableSlotReaderAdapter($slots))->forDate('tenant-1', '2026-08-03');

        foreach ($result as $slot) {
            self::assertStringNotContainsString('super secret internal detail', $slot->localStart.$slot->localEnd.$slot->timezone);
        }
    }
}
