<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Clinic\Domain;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidClinicOperationalTimeException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\BookingAppointmentDuration;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\BookingCapacityPerSlot;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClinicBookingConfigurationTest extends TestCase
{
    #[DataProvider('durations')]
    public function test_approved_durations_are_deterministic(int $minutes): void
    {
        $duration = new BookingAppointmentDuration($minutes);
        self::assertSame($minutes, $duration->minutes);
        self::assertTrue($duration->equals(new BookingAppointmentDuration($minutes)));
    }

    /** @return iterable<array{int}> */
    public static function durations(): iterable
    {
        foreach ([15, 20, 30, 45, 60] as $duration) {
            yield [$duration];
        }
    }

    #[DataProvider('invalidDurations')]
    public function test_arbitrary_duration_is_rejected(int $minutes): void
    {
        $this->expectException(InvalidClinicOperationalTimeException::class);
        new BookingAppointmentDuration($minutes);
    }

    /** @return iterable<array{int}> */
    public static function invalidDurations(): iterable
    {
        yield [0];
        yield [10];
        yield [25];
        yield [61];
    }

    #[DataProvider('capacities')]
    public function test_capacity_bounds(int $capacity, bool $valid): void
    {
        if (! $valid) {
            $this->expectException(InvalidClinicOperationalTimeException::class);
        }
        $value = new BookingCapacityPerSlot($capacity);
        if ($valid) {
            self::assertTrue($value->equals(new BookingCapacityPerSlot($capacity)));
        }
    }

    /** @return iterable<array{int, bool}> */
    public static function capacities(): iterable
    {
        yield [0, false];
        yield [1, true];
        yield [10, true];
        yield [11, false];
    }
}
