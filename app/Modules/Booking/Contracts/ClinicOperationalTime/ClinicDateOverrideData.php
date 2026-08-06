<?php

declare(strict_types=1);

namespace App\Modules\Booking\Contracts\ClinicOperationalTime;

final readonly class ClinicDateOverrideData
{
    /** @param list<ClinicOperatingIntervalData> $intervals */
    public function __construct(
        public string $localDate,
        public bool $closed,
        public array $intervals,
    ) {}
}
