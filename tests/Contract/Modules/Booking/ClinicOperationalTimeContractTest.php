<?php

declare(strict_types=1);

namespace Tests\Contract\Modules\Booking;

use App\Modules\Booking\Contracts\ClinicOperationalTime\ClinicOperationalTimeData;
use App\Modules\Booking\Contracts\ClinicOperationalTime\ClinicOperationalTimeNotFoundException;
use App\Modules\Booking\Contracts\ClinicOperationalTime\ClinicOperationalTimeReaderInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\ClinicRepositoryInterface;
use App\Modules\WebsiteBuilder\Domain\Clinic;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\ClinicId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\IanaTimezone;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\LocalTime;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\OpeningInterval;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WeeklyOperatingHours;
use App\Modules\WebsiteBuilder\Infrastructure\Queries\BookingClinicOperationalTimeAdapter;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ClinicOperationalTimeContractTest extends TestCase
{
    public function test_trusted_tenant_resolves_read_only_infrastructure_neutral_data(): void
    {
        $clinic = $this->clinic();
        $reader = new BookingClinicOperationalTimeAdapter($this->repository($clinic));

        $result = $reader->forTrustedTenant($clinic->tenantId->value);

        self::assertInstanceOf(ClinicOperationalTimeReaderInterface::class, $reader);
        self::assertInstanceOf(ClinicOperationalTimeData::class, $result);
        self::assertSame($clinic->id->value, $result->clinicId);
        self::assertSame($clinic->tenantId->value, $result->tenantId);
        self::assertSame('Asia/Kuala_Lumpur', $result->timezone);
        self::assertSame('09:00', $result->operatingIntervals[0]->opensAt);
    }

    public function test_missing_or_cross_tenant_profile_fails_explicitly_without_clinic_id_input(): void
    {
        $reader = new BookingClinicOperationalTimeAdapter($this->repository($this->clinic()));

        $this->expectException(ClinicOperationalTimeNotFoundException::class);

        $reader->forTrustedTenant($this->uuid(9));
    }

    private function repository(Clinic $clinic): ClinicRepositoryInterface
    {
        return new class($clinic) implements ClinicRepositoryInterface
        {
            public function __construct(private readonly Clinic $clinic) {}

            public function findById(TenantId $tenantId, ClinicId $clinicId): ?Clinic
            {
                return $tenantId->value === $this->clinic->tenantId->value
                    && $clinicId->value === $this->clinic->id->value ? $this->clinic : null;
            }

            public function findByTenantId(TenantId $tenantId): ?Clinic
            {
                return $tenantId->value === $this->clinic->tenantId->value ? $this->clinic : null;
            }

            public function save(Clinic $clinic): void {}
        };
    }

    private function clinic(): Clinic
    {
        return Clinic::create(
            new ClinicId($this->uuid(1)),
            new TenantId($this->uuid(2)),
            new IanaTimezone('Asia/Kuala_Lumpur'),
            new WeeklyOperatingHours([1 => [new OpeningInterval(new LocalTime('09:00'), new LocalTime('17:00'))]]),
            new DateTimeImmutable('2026-08-04T00:00:00Z'),
        );
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
