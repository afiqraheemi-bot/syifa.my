<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\ClinicBooking;

use App\Modules\WebsiteBuilder\Application\Exceptions\ClinicNotFoundException;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorization;
use App\Modules\WebsiteBuilder\Contracts\Repositories\ClinicRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Transactions\ClinicTransactionInterface;
use App\Modules\WebsiteBuilder\Domain\Exceptions\StaleClinicWriteException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\ClinicId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\IanaTimezone;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\LocalTime;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\OpeningInterval;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WeeklyOperatingHours;

final readonly class ManageClinicBusinessHoursService
{
    public function __construct(
        private ClinicRepositoryInterface $clinics,
        private ClinicTransactionInterface $transactions,
        private WebsiteAuthorization $authorization,
    ) {}

    public function update(UpdateClinicBusinessHoursCommand $command): ClinicBookingScheduleData
    {
        $tenant = new TenantId($command->tenantId);
        $clinicId = new ClinicId($command->clinicId);
        $this->authorization->assertCanManageClinicBooking($command->authorization, $tenant);

        return $this->transactions->run(function () use ($command, $tenant, $clinicId): ClinicBookingScheduleData {
            $clinic = $this->clinics->findById($tenant, $clinicId)
                ?? throw new ClinicNotFoundException('Clinic was not found in the authorized Tenant.');
            if ($clinic->version() !== $command->expectedVersion) {
                throw new StaleClinicWriteException('Clinic Business Hours changed since they were loaded.');
            }

            /** @var array<int, list<OpeningInterval>> $intervals */
            $intervals = [];
            foreach ($command->operatingIntervals as $interval) {
                $intervals[$interval['day']][] = new OpeningInterval(
                    new LocalTime($interval['opens_at']),
                    new LocalTime($interval['closes_at']),
                );
            }

            $clinic->reconfigureOperationalTime(
                new IanaTimezone($command->timezone),
                new WeeklyOperatingHours($intervals),
                $command->occurredAt,
            );
            $this->clinics->save($clinic);

            return ClinicBookingScheduleData::fromClinic($clinic);
        });
    }
}
