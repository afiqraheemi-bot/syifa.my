<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Clinic\Persistence;

use App\Modules\WebsiteBuilder\Domain\Clinic;
use App\Modules\WebsiteBuilder\Domain\Exceptions\StaleClinicWriteException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\BookingAppointmentDuration;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\BookingCapacityPerSlot;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\ClinicBookingConfiguration;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\ClinicId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\IanaTimezone;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\LocalTime;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\OpeningInterval;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WeeklyOperatingHours;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Exceptions\InvalidClinicStorageStateException;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\ClinicPersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Repositories\PostgresClinicRepository;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

final class PostgresClinicRepositoryTest extends TestCase
{
    private const string CONNECTION = 'clinic_postgres_integration';

    private ?ConnectionInterface $connection = null;

    private ?Migration $tenantMigration = null;

    private ?Migration $clinicMigration = null;

    private ?Migration $configurationMigration = null;

    private ?PostgresClinicRepository $repository = null;

    protected function setUp(): void
    {
        parent::setUp();
        $dsn = getenv('CLINIC_POSTGRES_TEST_DSN') ?: getenv('BOOKING_POSTGRES_TEST_DSN') ?: getenv('SUBSCRIPTION_BILLING_POSTGRES_TEST_DSN');
        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Requires CLINIC_POSTGRES_TEST_DSN (or an approved existing PostgreSQL test DSN) for a disposable database.');
        }

        config()->set('database.default', self::CONNECTION);
        config()->set('database.connections.'.self::CONNECTION, [
            'driver' => 'pgsql', 'url' => $dsn, 'charset' => 'utf8', 'prefix' => '',
            'prefix_indexes' => true, 'search_path' => 'public', 'sslmode' => 'prefer', 'timezone' => 'UTC',
        ]);
        DB::purge(self::CONNECTION);
        $this->connection = DB::connection(self::CONNECTION);
        Schema::connection(self::CONNECTION)->dropIfExists('clinic_operating_intervals');
        Schema::connection(self::CONNECTION)->dropIfExists('clinics');
        Schema::connection(self::CONNECTION)->dropIfExists('clinic_owner_authorities');
        Schema::connection(self::CONNECTION)->dropIfExists('tenants');

        $tenantMigration = require base_path('database/migrations/tenant_management/2026_07_13_000001_create_tenant_aggregate_tables.php');
        $clinicMigration = require base_path('database/migrations/website_builder/2026_08_04_000001_create_clinic_operational_time_tables.php');
        $configurationMigration = require base_path('database/migrations/website_builder/2026_08_05_000001_add_booking_configuration_to_clinics.php');
        self::assertInstanceOf(Migration::class, $tenantMigration);
        self::assertInstanceOf(Migration::class, $clinicMigration);
        self::assertInstanceOf(Migration::class, $configurationMigration);
        $this->tenantMigration = $tenantMigration;
        $this->clinicMigration = $clinicMigration;
        $this->configurationMigration = $configurationMigration;
        $tenantMigration->up();
        $clinicMigration->up();
        $configurationMigration->up();
        $this->repository = new PostgresClinicRepository($this->connection, new ClinicPersistenceMapper);
        $this->insertTenant(2);
        $this->insertTenant(3);
    }

    protected function tearDown(): void
    {
        $this->configurationMigration?->down();
        $this->clinicMigration?->down();
        $this->tenantMigration?->down();
        DB::purge(self::CONNECTION);
        parent::tearDown();
    }

    public function test_profile_persists_and_round_trips_timezone_and_ordered_weekly_hours(): void
    {
        $clinic = $this->clinic();
        $this->repository()->save($clinic);

        $loaded = $this->repository()->findByTenantId($clinic->tenantId);

        self::assertNotNull($loaded);
        self::assertSame(1, $clinic->version());
        self::assertSame($clinic->id->value, $loaded->id->value);
        self::assertSame('Asia/Kuala_Lumpur', $loaded->timezone()->value);
        self::assertSame(30, $loaded->bookingConfiguration()->appointmentDuration->minutes);
        self::assertSame(2, $loaded->bookingConfiguration()->capacityPerSlot->value);
        self::assertSame(['09:00', '13:00'], array_map(
            static fn (OpeningInterval $interval): string => $interval->opensAt->value,
            $loaded->weeklyOperatingHours()->all()[1],
        ));
        self::assertSame([], $loaded->weeklyOperatingHours()->all()[7]);
    }

    public function test_tenant_scoped_lookup_cannot_resolve_another_tenants_clinic(): void
    {
        $clinic = $this->clinic();
        $this->repository()->save($clinic);

        self::assertNull($this->repository()->findById(new TenantId($this->uuid(3)), $clinic->id));
        self::assertNull($this->repository()->findByTenantId(new TenantId($this->uuid(3))));
    }

    public function test_database_enforces_exactly_one_clinic_per_tenant_and_tenant_existence(): void
    {
        $this->repository()->save($this->clinic());

        try {
            $this->repository()->save($this->clinic(id: 9));
            self::fail('Duplicate Clinic for Tenant should fail.');
        } catch (QueryException) {
            self::assertSame(1, $this->connection()->table('clinics')->count());
        }

        $this->expectException(QueryException::class);
        $this->repository()->save($this->clinic(id: 10, tenant: 99));
    }

    public function test_reconfiguration_round_trips_and_stale_write_is_rejected(): void
    {
        $clinic = $this->clinic();
        $this->repository()->save($clinic);
        $first = $this->repository()->findByTenantId($clinic->tenantId);
        $stale = $this->repository()->findByTenantId($clinic->tenantId);
        self::assertNotNull($first);
        self::assertNotNull($stale);

        $first->reconfigureOperationalTime(new IanaTimezone('Asia/Singapore'), new WeeklyOperatingHours([]), $this->time('+1 day'));
        $this->repository()->save($first);
        $stale->reconfigureOperationalTime(new IanaTimezone('Asia/Singapore'), new WeeklyOperatingHours([]), $this->time('+1 day'));

        $this->expectException(StaleClinicWriteException::class);
        $this->repository()->save($stale);
    }

    public function test_invalid_persisted_timezone_fails_closed(): void
    {
        $clinic = $this->clinic();
        $this->repository()->save($clinic);
        $this->connection()->table('clinics')->where('id', $clinic->id->value)->update(['timezone' => '+08:00']);

        $this->expectException(InvalidClinicStorageStateException::class);

        $this->repository()->findByTenantId($clinic->tenantId);
    }

    public function test_invalid_persisted_overlapping_schedule_fails_closed(): void
    {
        $clinic = $this->clinic();
        $this->repository()->save($clinic);
        $this->connection()->table('clinic_operating_intervals')->insert([
            'clinic_id' => $clinic->id->value,
            'day_of_week' => 1,
            'opens_at' => '11:00',
            'closes_at' => '14:00',
        ]);

        $this->expectException(InvalidClinicStorageStateException::class);

        $this->repository()->findByTenantId($clinic->tenantId);
    }

    public function test_outer_transaction_rollback_removes_clinic_and_intervals(): void
    {
        try {
            $this->connection()->transaction(function (): never {
                $this->repository()->save($this->clinic());
                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException $exception) {
            self::assertSame('rollback', $exception->getMessage());
        }

        self::assertSame(0, $this->connection()->table('clinics')->count());
        self::assertSame(0, $this->connection()->table('clinic_operating_intervals')->count());
    }

    public function test_migration_is_reversible(): void
    {
        self::assertTrue(Schema::connection(self::CONNECTION)->hasTable('clinics'));
        self::assertTrue(Schema::connection(self::CONNECTION)->hasTable('clinic_operating_intervals'));
        $this->configurationMigration?->down();
        $this->configurationMigration = null;
        $this->clinicMigration?->down();
        $this->clinicMigration = null;
        self::assertFalse(Schema::connection(self::CONNECTION)->hasTable('clinics'));
    }

    private function clinic(int $id = 1, int $tenant = 2): Clinic
    {
        return Clinic::create(
            new ClinicId($this->uuid($id)),
            new TenantId($this->uuid($tenant)),
            new IanaTimezone('Asia/Kuala_Lumpur'),
            new WeeklyOperatingHours([
                1 => [
                    new OpeningInterval(new LocalTime('13:00'), new LocalTime('17:00')),
                    new OpeningInterval(new LocalTime('09:00'), new LocalTime('12:00')),
                ],
            ]),
            $this->time(),
            new ClinicBookingConfiguration(new BookingAppointmentDuration(30), new BookingCapacityPerSlot(2)),
        );
    }

    private function insertTenant(int $suffix): void
    {
        $time = $this->time()->format('Y-m-d H:i:sP');
        $this->connection()->table('tenants')->insert([
            'id' => $this->uuid($suffix), 'status' => 'active', 'version' => 1,
            'provisioned_at' => $time, 'activated_at' => $time,
            'created_at' => $time, 'updated_at' => $time,
        ]);
    }

    private function repository(): PostgresClinicRepository
    {
        self::assertNotNull($this->repository);

        return $this->repository;
    }

    private function connection(): ConnectionInterface
    {
        self::assertNotNull($this->connection);

        return $this->connection;
    }

    private function time(string $modifier = ''): DateTimeImmutable
    {
        $time = new DateTimeImmutable('2026-08-04T00:00:00Z');

        return $modifier === '' ? $time : $time->modify($modifier);
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
