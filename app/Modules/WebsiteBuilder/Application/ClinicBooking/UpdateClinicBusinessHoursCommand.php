<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\ClinicBooking;

use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use DateTimeImmutable;

final readonly class UpdateClinicBusinessHoursCommand
{
    /** @param list<array{day: int, opens_at: string, closes_at: string}> $operatingIntervals */
    public function __construct(
        public string $tenantId,
        public string $clinicId,
        public WebsiteAuthorizationContext $authorization,
        public int $expectedVersion,
        public string $timezone,
        public array $operatingIntervals,
        public DateTimeImmutable $occurredAt,
    ) {}
}
