<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Clinic\Infrastructure;

use App\Modules\WebsiteBuilder\Domain\Clinic;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\ClinicId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\IanaTimezone;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\LocalTime;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\OpeningInterval;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WeeklyOperatingHours;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\ClinicPersistenceMapper;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ClinicPersistenceMapperTest extends TestCase
{
    public function test_mapping_round_trips_every_owned_value(): void
    {
        $clinic = Clinic::create(
            new ClinicId($this->uuid(1)),
            new TenantId($this->uuid(2)),
            new IanaTimezone('Asia/Kuala_Lumpur'),
            new WeeklyOperatingHours([1 => [new OpeningInterval(new LocalTime('09:00'), new LocalTime('17:00'))]]),
            new DateTimeImmutable('2026-08-04T00:00:00Z'),
        );
        $clinic->synchronizeVersion(1);
        $mapper = new ClinicPersistenceMapper;

        $mapped = $mapper->toDomain($mapper->toRecord($clinic));

        self::assertSame($clinic->id->value, $mapped->id->value);
        self::assertSame($clinic->tenantId->value, $mapped->tenantId->value);
        self::assertSame($clinic->timezone()->value, $mapped->timezone()->value);
        self::assertSame('17:00', $mapped->weeklyOperatingHours()->all()[1][0]->closesAt->value);
        self::assertSame(1, $mapped->version());
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
