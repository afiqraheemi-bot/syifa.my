<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Queries;

use App\Modules\Booking\Contracts\ClinicOperationalTime\ClinicDateOverrideData;
use App\Modules\Booking\Contracts\ClinicOperationalTime\ClinicOperatingIntervalData;
use App\Modules\Booking\Contracts\ClinicOperationalTime\ClinicOperationalTimeData;
use App\Modules\Booking\Contracts\ClinicOperationalTime\ClinicOperationalTimeNotFoundException;
use App\Modules\Booking\Contracts\ClinicOperationalTime\ClinicOperationalTimeReaderInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\ClinicBookingDateOverrideRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\ClinicRepositoryInterface;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;

final readonly class BookingClinicOperationalTimeAdapter implements ClinicOperationalTimeReaderInterface
{
    public function __construct(
        private ClinicRepositoryInterface $clinics,
        private ClinicBookingDateOverrideRepositoryInterface $overrides,
    ) {}

    public function forTrustedTenant(string $tenantId): ClinicOperationalTimeData
    {
        $trustedTenantId = new TenantId($tenantId);
        $clinic = $this->clinics->findByTenantId($trustedTenantId);
        if ($clinic === null) {
            throw new ClinicOperationalTimeNotFoundException('Clinic operational time is unavailable for the trusted Tenant.');
        }

        $intervals = [];
        foreach ($clinic->weeklyBookingAvailability()->all() as $day => $dayIntervals) {
            foreach ($dayIntervals as $interval) {
                $intervals[] = new ClinicOperatingIntervalData(
                    $day,
                    $interval->opensAt->value,
                    $interval->closesAt->value,
                );
            }
        }

        $configuration = $clinic->bookingConfigurationOrNull();
        $dateOverrides = array_map(
            static fn ($override): ClinicDateOverrideData => new ClinicDateOverrideData(
                $override->localDate,
                $override->closed,
                array_map(
                    static fn (array $interval): ClinicOperatingIntervalData => new ClinicOperatingIntervalData(
                        1,
                        $interval['opens_at'],
                        $interval['closes_at'],
                    ),
                    $override->intervals,
                ),
            ),
            $this->overrides->allForClinic($clinic->id),
        );

        return new ClinicOperationalTimeData(
            $clinic->id->value,
            $clinic->tenantId->value,
            $clinic->timezone()->value,
            $intervals,
            $configuration?->appointmentDuration->minutes,
            $configuration?->capacityPerSlot->value,
            $dateOverrides,
        );
    }
}
