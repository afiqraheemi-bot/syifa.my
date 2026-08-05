<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\ClinicBooking;

use App\Modules\WebsiteBuilder\Domain\Clinic;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\DayOfWeek;

final readonly class ClinicBookingScheduleData
{
    /** @param list<array{day: int, opens_at: string, closes_at: string}> $operatingIntervals */
    public function __construct(
        public string $clinicId,
        public int $version,
        public string $timezone,
        public array $operatingIntervals,
        public ?int $appointmentDurationMinutes,
        public ?int $bookingCapacityPerSlot,
    ) {}

    public static function fromClinic(Clinic $clinic): self
    {
        $intervals = [];
        foreach (DayOfWeek::cases() as $day) {
            foreach ($clinic->weeklyOperatingHours()->intervalsFor($day) as $interval) {
                $intervals[] = [
                    'day' => $day->value,
                    'opens_at' => $interval->opensAt->value,
                    'closes_at' => $interval->closesAt->value,
                ];
            }
        }

        $configuration = $clinic->bookingConfigurationOrNull();

        return new self(
            $clinic->id->value,
            $clinic->version(),
            $clinic->timezone()->value,
            $intervals,
            $configuration?->appointmentDuration->minutes,
            $configuration?->capacityPerSlot->value,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'clinic_id' => $this->clinicId,
            'version' => $this->version,
            'timezone' => $this->timezone,
            'operating_intervals' => $this->operatingIntervals,
            'appointment_duration_minutes' => $this->appointmentDurationMinutes,
            'booking_capacity_per_slot' => $this->bookingCapacityPerSlot,
        ];
    }
}
