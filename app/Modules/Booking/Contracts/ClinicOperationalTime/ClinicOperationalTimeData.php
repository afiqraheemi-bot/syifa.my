<?php

declare(strict_types=1);

namespace App\Modules\Booking\Contracts\ClinicOperationalTime;

final readonly class ClinicOperationalTimeData
{
    /**
     * @param  list<ClinicOperatingIntervalData>  $operatingIntervals
     * @param  list<ClinicDateOverrideData>  $dateOverrides
     */
    public function __construct(
        public string $clinicId,
        public string $tenantId,
        public string $timezone,
        public array $operatingIntervals,
        public ?int $appointmentDurationMinutes,
        public ?int $bookingCapacityPerSlot,
        public array $dateOverrides = [],
    ) {}
}
