<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\ValueObjects;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidClinicOperationalTimeException;

final readonly class WeeklyOperatingHours
{
    /** @var array<int, list<OpeningInterval>> */
    private array $intervalsByDay;

    /** @param array<int, list<OpeningInterval>> $intervalsByDay */
    public function __construct(array $intervalsByDay)
    {
        $normalized = [];

        foreach ($intervalsByDay as $day => $intervals) {
            if (DayOfWeek::tryFrom($day) === null) {
                throw new InvalidClinicOperationalTimeException('Operating-hours day must use ISO weekday 1 through 7.');
            }
            usort($intervals, static fn (OpeningInterval $left, OpeningInterval $right): int => $left->opensAt->minutesSinceMidnight <=> $right->opensAt->minutesSinceMidnight);

            $previous = null;
            foreach ($intervals as $interval) {
                if ($previous !== null && $interval->opensAt->minutesSinceMidnight < $previous->closesAt->minutesSinceMidnight) {
                    throw new InvalidClinicOperationalTimeException('Operating intervals on the same day must not overlap or duplicate.');
                }
                $previous = $interval;
            }

            $normalized[$day] = $intervals;
        }

        for ($day = 1; $day <= 7; $day++) {
            $normalized[$day] ??= [];
        }
        ksort($normalized);
        $this->intervalsByDay = $normalized;
    }

    /** @return list<OpeningInterval> */
    public function intervalsFor(DayOfWeek $day): array
    {
        return $this->intervalsByDay[$day->value];
    }

    /** @return array<int, list<OpeningInterval>> */
    public function all(): array
    {
        return $this->intervalsByDay;
    }
}
