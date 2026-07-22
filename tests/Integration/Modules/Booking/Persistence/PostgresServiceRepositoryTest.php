<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Booking\Persistence;

use App\Modules\Booking\Domain\Exceptions\StaleServiceWriteException;
use App\Modules\Booking\Domain\Service;
use App\Modules\Booking\Domain\ValueObjects\DurationMinutes;
use App\Modules\Booking\Domain\ValueObjects\ServiceDescription;
use App\Modules\Booking\Domain\ValueObjects\ServiceId;
use App\Modules\Booking\Domain\ValueObjects\ServiceName;
use App\Modules\Booking\Domain\ValueObjects\SortOrder;
use App\Modules\Booking\Domain\ValueObjects\TenantId;
use App\Modules\Booking\Infrastructure\Persistence\Mappers\ServicePersistenceMapper;
use App\Modules\Booking\Infrastructure\Persistence\Repositories\PostgresServiceRepository;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PostgresServiceRepositoryTest extends TestCase
{
    private const string CONNECTION_NAME = 'service_postgres_integration';

    private ?ConnectionInterface $connection = null;

    private ?PostgresServiceRepository $repository = null;

    private ?Migration $migration = null;

    protected function setUp(): void
    {
        parent::setUp();

        $dsn = getenv('BOOKING_POSTGRES_TEST_DSN') ?: getenv('SUBSCRIPTION_BILLING_POSTGRES_TEST_DSN');

        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Requires BOOKING_POSTGRES_TEST_DSN (or SUBSCRIPTION_BILLING_POSTGRES_TEST_DSN) for a dedicated disposable PostgreSQL database.');
        }

        config()->set('database.default', self::CONNECTION_NAME);
        config()->set('database.connections.'.self::CONNECTION_NAME, [
            'driver' => 'pgsql',
            'url' => $dsn,
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
            'timezone' => 'UTC',
        ]);
        DB::purge(self::CONNECTION_NAME);
        $this->connection = DB::connection(self::CONNECTION_NAME);
        Schema::connection(self::CONNECTION_NAME)->dropIfExists('services');

        $migration = require base_path('database/migrations/booking/2026_07_31_000001_create_services_table.php');
        self::assertInstanceOf(Migration::class, $migration);
        $this->migration = $migration;
        $this->migration->up();

        $this->repository = new PostgresServiceRepository($this->connection, new ServicePersistenceMapper);
    }

    protected function tearDown(): void
    {
        if ($this->migration !== null) {
            $this->migration->down();
        }

        DB::purge(self::CONNECTION_NAME);
        parent::tearDown();
    }

    public function test_persist_and_reload_a_newly_registered_service(): void
    {
        $service = $this->service();
        $this->repository()->save($service);

        $reloaded = $this->repository()->findById($service->tenantId, $service->id);

        self::assertNotNull($reloaded);
        self::assertSame(1, $reloaded->version());
        self::assertSame($service->tenantId->value, $reloaded->tenantId->value);
        self::assertSame($service->name->value, $reloaded->name->value);
        self::assertSame($service->description?->value, $reloaded->description?->value);
        self::assertSame($service->durationMinutes?->value, $reloaded->durationMinutes?->value);
        self::assertSame($service->sortOrder->value, $reloaded->sortOrder->value);
        self::assertSame('active', $reloaded->status()->value);
        self::assertSame($service->createdAt->format(DATE_ATOM), $reloaded->createdAt->format(DATE_ATOM));
        self::assertSame($service->updatedAt()->format(DATE_ATOM), $reloaded->updatedAt()->format(DATE_ATOM));
    }

    public function test_service_without_description_or_duration_round_trips_as_null(): void
    {
        $service = $this->service(description: null, durationMinutes: null);
        $this->repository()->save($service);

        $reloaded = $this->repository()->findById($service->tenantId, $service->id);

        self::assertNotNull($reloaded);
        self::assertNull($reloaded->description);
        self::assertNull($reloaded->durationMinutes);
    }

    public function test_find_by_id_does_not_cross_the_tenant_boundary(): void
    {
        $service = $this->service();
        $this->repository()->save($service);

        self::assertNull($this->repository()->findById(new TenantId($this->uuid(3)), $service->id));
    }

    public function test_find_active_returns_only_active_services_for_the_tenant_in_sort_order(): void
    {
        $tenantId = new TenantId($this->uuid(2));
        $first = $this->service(id: 1, sortOrder: 2, name: 'Second');
        $second = $this->service(id: 2, sortOrder: 1, name: 'First');
        $inactive = $this->service(id: 3, sortOrder: 0, name: 'Retired');
        $otherTenant = $this->service(id: 4, tenantId: 3, sortOrder: 0, name: 'OtherTenantService');

        $inactive->deactivate($this->time());

        $this->repository()->save($first);
        $this->repository()->save($second);
        $this->repository()->save($inactive);
        $this->repository()->save($otherTenant);

        $active = $this->repository()->findActive($tenantId);

        self::assertCount(2, $active);
        self::assertSame('First', $active[0]->name->value);
        self::assertSame('Second', $active[1]->name->value);
    }

    public function test_find_all_returns_every_status_for_the_tenant(): void
    {
        $tenantId = new TenantId($this->uuid(2));
        $active = $this->service(id: 1, sortOrder: 0, name: 'Active One');
        $inactive = $this->service(id: 2, sortOrder: 1, name: 'Inactive One');
        $inactive->deactivate($this->time());

        $this->repository()->save($active);
        $this->repository()->save($inactive);

        $all = $this->repository()->findAll($tenantId);

        self::assertCount(2, $all);
    }

    public function test_exists_by_name_is_scoped_to_the_owning_tenant(): void
    {
        $this->repository()->save($this->service());

        self::assertTrue($this->repository()->existsByName(new TenantId($this->uuid(2)), 'Dental Cleaning'));
        self::assertFalse($this->repository()->existsByName(new TenantId($this->uuid(3)), 'Dental Cleaning'));
        self::assertFalse($this->repository()->existsByName(new TenantId($this->uuid(2)), 'Whitening'));
    }

    public function test_database_rejects_a_duplicate_name_within_the_same_tenant(): void
    {
        $this->repository()->save($this->service());

        $this->expectException(QueryException::class);

        $this->repository()->save($this->service(id: 9));
    }

    public function test_same_name_is_allowed_across_different_tenants(): void
    {
        $this->repository()->save($this->service());
        $this->repository()->save($this->service(id: 9, tenantId: 3));

        self::assertCount(1, $this->repository()->findAll(new TenantId($this->uuid(2))));
        self::assertCount(1, $this->repository()->findAll(new TenantId($this->uuid(3))));
    }

    public function test_optimistic_locking_rejects_a_stale_write(): void
    {
        $service = $this->service();
        $this->repository()->save($service);

        $firstCopy = $this->repository()->findById($service->tenantId, $service->id);
        $staleCopy = $this->repository()->findById($service->tenantId, $service->id);
        self::assertNotNull($firstCopy);
        self::assertNotNull($staleCopy);

        $firstCopy->deactivate($this->time());
        $this->repository()->save($firstCopy);

        $staleCopy->deactivate($this->time());

        $this->expectException(StaleServiceWriteException::class);

        $this->repository()->save($staleCopy);
    }

    private function service(
        int $id = 1,
        int $tenantId = 2,
        string $name = 'Dental Cleaning',
        ?string $description = 'Routine cleaning and checkup',
        ?int $durationMinutes = 30,
        int $sortOrder = 1,
    ): Service {
        return Service::register(
            new ServiceId($this->uuid($id)),
            new TenantId($this->uuid($tenantId)),
            new ServiceName($name),
            $description === null ? null : new ServiceDescription($description),
            $durationMinutes === null ? null : new DurationMinutes($durationMinutes),
            new SortOrder($sortOrder),
            $this->time(),
        );
    }

    private function repository(): PostgresServiceRepository
    {
        self::assertNotNull($this->repository);

        return $this->repository;
    }

    private function time(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-31T00:00:00Z');
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
