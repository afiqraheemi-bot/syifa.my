<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Clinic\Domain;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidClinicOperationalTimeException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\DayOfWeek;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\LocalTime;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\OpeningInterval;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WeeklyOperatingHours;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WeeklyOperatingHoursTest extends TestCase
{
    public function test_closed_days_and_valid_intervals_are_normalized_deterministically(): void
    {
        $hours = new WeeklyOperatingHours([
            2 => [$this->interval('13:00', '17:00'), $this->interval('09:00', '12:00')],
        ]);

        self::assertSame([], $hours->intervalsFor(DayOfWeek::Monday));
        self::assertSame(
            ['09:00', '13:00'],
            array_map(static fn (OpeningInterval $interval): string => $interval->opensAt->value, $hours->intervalsFor(DayOfWeek::Tuesday)),
        );
        self::assertCount(7, $hours->all());
    }

    public function test_back_to_back_half_open_intervals_are_supported(): void
    {
        $hours = new WeeklyOperatingHours([
            1 => [$this->interval('09:00', '12:00'), $this->interval('12:00', '17:00')],
        ]);

        self::assertCount(2, $hours->intervalsFor(DayOfWeek::Monday));
    }

    #[DataProvider('overlappingIntervals')]
    public function test_overlapping_and_duplicate_intervals_are_rejected(string $secondOpen, string $secondClose): void
    {
        $this->expectException(InvalidClinicOperationalTimeException::class);

        new WeeklyOperatingHours([
            1 => [$this->interval('09:00', '12:00'), $this->interval($secondOpen, $secondClose)],
        ]);
    }

    /** @return iterable<string, array{string, string}> */
    public static function overlappingIntervals(): iterable
    {
        yield 'overlap' => ['11:59', '13:00'];
        yield 'duplicate' => ['09:00', '12:00'];
    }

    #[DataProvider('invalidIntervals')]
    public function test_non_increasing_cross_midnight_and_sentinel_intervals_are_rejected(string $open, string $close): void
    {
        $this->expectException(InvalidClinicOperationalTimeException::class);

        $this->interval($open, $close);
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidIntervals(): iterable
    {
        yield 'equal' => ['09:00', '09:00'];
        yield 'cross midnight' => ['22:00', '02:00'];
        yield 'ambiguous full day' => ['00:00', '00:00'];
    }

    private function interval(string $opensAt, string $closesAt): OpeningInterval
    {
        return new OpeningInterval(new LocalTime($opensAt), new LocalTime($closesAt));
    }
}
