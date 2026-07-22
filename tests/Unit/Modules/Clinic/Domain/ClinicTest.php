<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Clinic\Domain;

use App\Modules\WebsiteBuilder\Domain\Clinic;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidClinicOperationalTimeException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\ClinicId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\IanaTimezone;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\LocalTime;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\OpeningInterval;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WeeklyOperatingHours;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ClinicTest extends TestCase
{
    public function test_clinic_retains_tenant_and_operational_time(): void
    {
        $clinic = $this->clinic();

        self::assertSame($this->uuid(2), $clinic->tenantId->value);
        self::assertSame('Asia/Kuala_Lumpur', $clinic->timezone()->value);
        self::assertSame('09:00', $clinic->weeklyOperatingHours()->all()[1][0]->opensAt->value);
        self::assertSame(0, $clinic->version());
    }

    public function test_operational_time_mutation_is_atomic_and_updates_timestamp(): void
    {
        $clinic = $this->clinic();
        $changedAt = new DateTimeImmutable('2026-08-05T00:00:00Z');
        $clinic->reconfigureOperationalTime(
            new IanaTimezone('Asia/Singapore'),
            new WeeklyOperatingHours([]),
            $changedAt,
        );

        self::assertSame('Asia/Singapore', $clinic->timezone()->value);
        self::assertSame([], $clinic->weeklyOperatingHours()->all()[1]);
        self::assertSame($changedAt, $clinic->updatedAt());
    }

    public function test_invalid_persisted_schedule_fails_reconstitution(): void
    {
        $this->expectException(InvalidClinicOperationalTimeException::class);

        Clinic::reconstitute(
            new ClinicId($this->uuid(1)),
            new TenantId($this->uuid(2)),
            new IanaTimezone('Asia/Kuala_Lumpur'),
            new WeeklyOperatingHours([]),
            new DateTimeImmutable('2026-08-04T00:00:00Z'),
            new DateTimeImmutable('2026-08-03T00:00:00Z'),
            1,
        );
    }

    private function clinic(): Clinic
    {
        return Clinic::create(
            new ClinicId($this->uuid(1)),
            new TenantId($this->uuid(2)),
            new IanaTimezone('Asia/Kuala_Lumpur'),
            new WeeklyOperatingHours([
                1 => [new OpeningInterval(new LocalTime('09:00'), new LocalTime('17:00'))],
            ]),
            new DateTimeImmutable('2026-08-04T00:00:00Z'),
        );
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
