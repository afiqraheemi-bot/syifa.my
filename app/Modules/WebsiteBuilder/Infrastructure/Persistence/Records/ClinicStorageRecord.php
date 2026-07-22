<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Persistence\Records;

use DateTimeImmutable;

final readonly class ClinicStorageRecord
{
    /** @param list<OperatingIntervalStorageRecord> $operatingIntervals */
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $timezone,
        public array $operatingIntervals,
        public DateTimeImmutable $domainCreatedAt,
        public DateTimeImmutable $domainUpdatedAt,
        public int $version,
    ) {}
}
