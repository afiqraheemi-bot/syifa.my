<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Persistence\Records;

use DateTimeImmutable;

final readonly class ClinicStorageRecord
{
    /**
     * @param  list<OperatingIntervalStorageRecord>  $operatingIntervals
     * @param  list<OperatingIntervalStorageRecord>  $bookingAvailabilityIntervals
     */
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $timezone,
        public array $operatingIntervals,
        public array $bookingAvailabilityIntervals,
        public DateTimeImmutable $domainCreatedAt,
        public DateTimeImmutable $domainUpdatedAt,
        public int $version,
        public ?int $appointmentDurationMinutes,
        public ?int $bookingCapacityPerSlot,
        public ?string $operationalPhone,
        public ?string $operationalEmail,
        public ?string $postalAddress,
        public ?string $whatsAppNumber,
        public ?float $latitude,
        public ?float $longitude,
    ) {}
}
