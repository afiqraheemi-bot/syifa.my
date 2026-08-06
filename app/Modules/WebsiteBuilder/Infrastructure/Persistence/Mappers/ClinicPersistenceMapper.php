<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers;

use App\Modules\WebsiteBuilder\Domain\Clinic;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\BookingAppointmentDuration;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\BookingCapacityPerSlot;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\ClinicBookingConfiguration;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\ClinicContactProfile;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\ClinicId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\IanaTimezone;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\LocalTime;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\OpeningInterval;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WeeklyOperatingHours;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Records\ClinicStorageRecord;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Records\OperatingIntervalStorageRecord;

final class ClinicPersistenceMapper
{
    public function toRecord(Clinic $clinic): ClinicStorageRecord
    {
        $intervals = [];
        foreach ($clinic->weeklyOperatingHours()->all() as $day => $dayIntervals) {
            foreach ($dayIntervals as $interval) {
                $intervals[] = new OperatingIntervalStorageRecord(
                    $day,
                    $interval->opensAt->value,
                    $interval->closesAt->value,
                );
            }
        }

        $configuration = $clinic->bookingConfigurationOrNull();
        $bookingAvailability = [];
        foreach ($clinic->weeklyBookingAvailability()->all() as $day => $dayIntervals) {
            foreach ($dayIntervals as $interval) {
                $bookingAvailability[] = new OperatingIntervalStorageRecord(
                    $day,
                    $interval->opensAt->value,
                    $interval->closesAt->value,
                );
            }
        }

        return new ClinicStorageRecord(
            $clinic->id->value,
            $clinic->tenantId->value,
            $clinic->timezone()->value,
            $intervals,
            $bookingAvailability,
            $clinic->createdAt,
            $clinic->updatedAt(),
            $clinic->version(),
            $configuration?->appointmentDuration->minutes,
            $configuration?->capacityPerSlot->value,
            $clinic->contactProfile()->operationalPhone,
            $clinic->contactProfile()->operationalEmail,
            $clinic->contactProfile()->postalAddress,
            $clinic->contactProfile()->whatsAppNumber,
            $clinic->contactProfile()->latitude,
            $clinic->contactProfile()->longitude,
        );
    }

    public function toDomain(ClinicStorageRecord $record): Clinic
    {
        $hours = [];
        foreach ($record->operatingIntervals as $interval) {
            $hours[$interval->dayOfWeek][] = new OpeningInterval(
                new LocalTime($interval->opensAt),
                new LocalTime($interval->closesAt),
            );
        }
        $bookingAvailability = [];
        foreach ($record->bookingAvailabilityIntervals as $interval) {
            $bookingAvailability[$interval->dayOfWeek][] = new OpeningInterval(
                new LocalTime($interval->opensAt),
                new LocalTime($interval->closesAt),
            );
        }

        return Clinic::reconstitute(
            new ClinicId($record->id),
            new TenantId($record->tenantId),
            new IanaTimezone($record->timezone),
            new WeeklyOperatingHours($hours),
            $record->domainCreatedAt,
            $record->domainUpdatedAt,
            $record->version,
            $record->appointmentDurationMinutes === null || $record->bookingCapacityPerSlot === null
                ? null
                : new ClinicBookingConfiguration(
                    new BookingAppointmentDuration($record->appointmentDurationMinutes),
                    new BookingCapacityPerSlot($record->bookingCapacityPerSlot),
                ),
            new ClinicContactProfile(
                $record->operationalPhone,
                $record->operationalEmail,
                $record->postalAddress,
                $record->whatsAppNumber,
                $record->latitude,
                $record->longitude,
            ),
            new WeeklyOperatingHours($bookingAvailability),
        );
    }
}
