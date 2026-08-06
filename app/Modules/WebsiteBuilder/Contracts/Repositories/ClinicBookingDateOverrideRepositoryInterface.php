<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\Repositories;

use App\Modules\WebsiteBuilder\Application\ClinicBooking\ClinicBookingDateOverrideData;
use App\Modules\WebsiteBuilder\Domain\Exceptions\StaleClinicWriteException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\ClinicId;

interface ClinicBookingDateOverrideRepositoryInterface
{
    /** @return list<ClinicBookingDateOverrideData> */
    public function allForClinic(ClinicId $clinicId): array;

    /**
     * @param  list<array{opens_at: string, closes_at: string}>  $intervals
     *
     * @throws StaleClinicWriteException
     */
    public function replace(ClinicId $clinicId, string $localDate, bool $closed, array $intervals, int $expectedVersion): ClinicBookingDateOverrideData;

    public function delete(ClinicId $clinicId, string $localDate, int $expectedVersion): void;
}
