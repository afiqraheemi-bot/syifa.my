<?php

declare(strict_types=1);

namespace App\Modules\Booking\Contracts\ClinicOperationalTime;

final readonly class ClinicOperatingIntervalData
{
    public function __construct(
        public int $dayOfWeek,
        public string $opensAt,
        public string $closesAt,
    ) {}
}
