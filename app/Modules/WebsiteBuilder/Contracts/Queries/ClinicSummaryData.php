<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\Queries;

final readonly class ClinicSummaryData
{
    public function __construct(
        public string $clinicId,
        public string $clinicName,
        public string $timezone,
        public bool $operationalProfileConfigured,
    ) {}
}
