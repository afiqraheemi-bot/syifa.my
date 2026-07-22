<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidClinicOperationalTimeException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\ClinicId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\IanaTimezone;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WeeklyOperatingHours;
use DateTimeImmutable;

final class Clinic
{
    private function __construct(
        public readonly ClinicId $id,
        public readonly TenantId $tenantId,
        private IanaTimezone $timezone,
        private WeeklyOperatingHours $weeklyOperatingHours,
        public readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private int $version,
    ) {}

    public static function create(
        ClinicId $id,
        TenantId $tenantId,
        IanaTimezone $timezone,
        WeeklyOperatingHours $weeklyOperatingHours,
        DateTimeImmutable $occurredAt,
    ): self {
        return new self($id, $tenantId, $timezone, $weeklyOperatingHours, $occurredAt, $occurredAt, 0);
    }

    public static function reconstitute(
        ClinicId $id,
        TenantId $tenantId,
        IanaTimezone $timezone,
        WeeklyOperatingHours $weeklyOperatingHours,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        int $version,
    ): self {
        if ($version < 1) {
            throw new InvalidClinicOperationalTimeException('A persisted Clinic requires a positive version.');
        }
        if ($updatedAt < $createdAt) {
            throw new InvalidClinicOperationalTimeException('A persisted Clinic cannot be updated before creation.');
        }

        return new self($id, $tenantId, $timezone, $weeklyOperatingHours, $createdAt, $updatedAt, $version);
    }

    public function reconfigureOperationalTime(
        IanaTimezone $timezone,
        WeeklyOperatingHours $weeklyOperatingHours,
        DateTimeImmutable $occurredAt,
    ): void {
        if ($occurredAt < $this->updatedAt) {
            throw new InvalidClinicOperationalTimeException('Clinic operational time cannot be changed in the past.');
        }

        $this->timezone = $timezone;
        $this->weeklyOperatingHours = $weeklyOperatingHours;
        $this->updatedAt = $occurredAt;
    }

    public function timezone(): IanaTimezone
    {
        return $this->timezone;
    }

    public function weeklyOperatingHours(): WeeklyOperatingHours
    {
        return $this->weeklyOperatingHours;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function synchronizeVersion(int $version): void
    {
        if ($version !== $this->version + 1) {
            throw new InvalidClinicOperationalTimeException('Clinic persistence version must advance by exactly one.');
        }
        $this->version = $version;
    }
}
