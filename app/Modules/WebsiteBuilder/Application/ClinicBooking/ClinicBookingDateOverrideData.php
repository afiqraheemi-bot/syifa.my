<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\ClinicBooking;

final readonly class ClinicBookingDateOverrideData
{
    /** @param list<array{opens_at: string, closes_at: string}> $intervals */
    public function __construct(
        public string $localDate,
        public bool $closed,
        public array $intervals,
        public int $version,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'local_date' => $this->localDate,
            'closed' => $this->closed,
            'intervals' => $this->intervals,
            'version' => $this->version,
        ];
    }
}
