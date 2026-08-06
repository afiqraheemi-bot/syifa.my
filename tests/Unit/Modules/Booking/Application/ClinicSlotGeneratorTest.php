<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Booking\Application;

use App\Modules\Booking\Application\Availability\ClinicSlotGenerator;
use App\Modules\Booking\Application\Exceptions\InvalidClinicBookingConfigurationException;
use App\Modules\Booking\Contracts\ClinicOperationalTime\ClinicDateOverrideData;
use App\Modules\Booking\Contracts\ClinicOperationalTime\ClinicOperatingIntervalData;
use App\Modules\Booking\Contracts\ClinicOperationalTime\ClinicOperationalTimeData;
use PHPUnit\Framework\TestCase;

final class ClinicSlotGeneratorTest extends TestCase
{
    public function test_generates_fixed_half_open_slots_and_omits_incomplete_trailing_time(): void
    {
        $slots = (new ClinicSlotGenerator)->generate($this->clinic([
            new ClinicOperatingIntervalData(1, '09:00', '10:40'),
        ]), '2026-08-10');

        self::assertSame(['09:00', '09:30', '10:00'], array_column($slots, 'localStart'));
        self::assertSame(['09:30', '10:00', '10:30'], array_column($slots, 'localEnd'));
        self::assertSame('2026-08-10T01:00:00+00:00', $slots[0]->startsAtUtc->format(DATE_ATOM));
    }

    public function test_supports_multiple_intervals_and_closed_days(): void
    {
        $clinic = $this->clinic([new ClinicOperatingIntervalData(1, '09:00', '10:00'), new ClinicOperatingIntervalData(1, '14:00', '15:00')]);

        self::assertCount(4, (new ClinicSlotGenerator)->generate($clinic, '2026-08-10'));
        self::assertSame([], (new ClinicSlotGenerator)->generate($clinic, '2026-08-11'));
    }

    public function test_fails_closed_for_missing_configuration_and_ambiguous_local_time(): void
    {
        $generator = new ClinicSlotGenerator;
        $this->expectException(InvalidClinicBookingConfigurationException::class);
        $generator->generate(new ClinicOperationalTimeData('clinic', 'tenant', 'America/New_York', [new ClinicOperatingIntervalData(7, '01:00', '02:00')], 30, 1), '2026-11-01');
    }

    public function test_closed_date_override_removes_all_weekly_slots(): void
    {
        $clinic = new ClinicOperationalTimeData(
            'clinic',
            'tenant',
            'Asia/Kuala_Lumpur',
            [new ClinicOperatingIntervalData(1, '09:00', '12:00')],
            30,
            1,
            [new ClinicDateOverrideData('2026-08-10', true, [])],
        );

        self::assertSame([], (new ClinicSlotGenerator)->generate($clinic, '2026-08-10'));
    }

    public function test_special_date_sessions_replace_weekly_hours_only_for_that_date(): void
    {
        $clinic = new ClinicOperationalTimeData(
            'clinic',
            'tenant',
            'Asia/Kuala_Lumpur',
            [new ClinicOperatingIntervalData(1, '09:00', '10:00')],
            30,
            1,
            [new ClinicDateOverrideData('2026-08-10', false, [
                new ClinicOperatingIntervalData(1, '15:00', '16:00'),
                new ClinicOperatingIntervalData(1, '20:00', '21:00'),
            ])],
        );

        $generator = new ClinicSlotGenerator;
        self::assertSame(['15:00', '15:30', '20:00', '20:30'], array_column($generator->generate($clinic, '2026-08-10'), 'localStart'));
        self::assertSame(['09:00', '09:30'], array_column($generator->generate($clinic, '2026-08-17'), 'localStart'));
    }

    /** @param list<ClinicOperatingIntervalData> $intervals */
    private function clinic(array $intervals): ClinicOperationalTimeData
    {
        return new ClinicOperationalTimeData('clinic', 'tenant', 'Asia/Kuala_Lumpur', $intervals, 30, 2);
    }
}
