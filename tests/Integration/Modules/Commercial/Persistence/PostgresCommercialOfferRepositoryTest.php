<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Commercial\Persistence;

use App\Modules\Commercial\Domain\CommercialOffer;
use App\Modules\Commercial\Domain\Exceptions\StaleCommercialOfferWriteException;
use App\Modules\Commercial\Domain\ValueObjects\CheckoutSnapshot;
use App\Modules\Commercial\Domain\ValueObjects\ClinicRegistrationReference;
use App\Modules\Commercial\Domain\ValueObjects\CommercialOfferId;
use App\Modules\Commercial\Domain\ValueObjects\CommercialOfferLineItem;
use App\Modules\Commercial\Domain\ValueObjects\OfferExpiry;
use App\Modules\Commercial\Domain\ValueObjects\PlatformIdentityReference;
use App\Modules\Commercial\Domain\ValueObjects\PriceSnapshot;
use App\Modules\Commercial\Domain\ValueObjects\TenantId;
use App\Modules\Commercial\Infrastructure\Persistence\Mappers\CommercialOfferPersistenceMapper;
use App\Modules\Commercial\Infrastructure\Persistence\Repositories\PostgresCommercialOfferRepository;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Throwable;

final class PostgresCommercialOfferRepositoryTest extends TestCase
{
    private const string CONNECTION_NAME = 'commercial_postgres_integration';

    private ?ConnectionInterface $connection = null;

    private ?PostgresCommercialOfferRepository $repository = null;

    private ?Migration $migration = null;

    private ?Migration $tenantIdMigration = null;

    private ?Migration $provenanceMigration = null;

    private ?Migration $ownershipMigration = null;

    protected function setUp(): void
    {
        parent::setUp();

        $dsn = getenv('COMMERCIAL_POSTGRES_TEST_DSN');

        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Requires COMMERCIAL_POSTGRES_TEST_DSN for a dedicated disposable PostgreSQL database.');
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
        ]);
        DB::purge(self::CONNECTION_NAME);
        $this->connection = DB::connection(self::CONNECTION_NAME);
        Schema::dropIfExists('commercial_offer_line_items');
        Schema::dropIfExists('commercial_offers');

        $migration = require base_path('database/migrations/commercial/2026_07_21_000001_create_commercial_offer_tables.php');
        self::assertInstanceOf(Migration::class, $migration);
        $this->migration = $migration;
        $migration->up();

        $tenantIdMigration = require base_path('database/migrations/commercial/2026_07_26_000001_add_tenant_id_to_commercial_offers.php');
        self::assertInstanceOf(Migration::class, $tenantIdMigration);
        $this->tenantIdMigration = $tenantIdMigration;
        $tenantIdMigration->up();

        $provenanceMigration = require base_path('database/migrations/commercial/2026_07_30_000001_add_renewal_offer_provenance.php');
        self::assertInstanceOf(Migration::class, $provenanceMigration);
        $this->provenanceMigration = $provenanceMigration;
        $provenanceMigration->up();

        $ownershipMigration = require base_path('database/migrations/commercial/2026_08_28_000001_correct_initial_commercial_offer_ownership.php');
        self::assertInstanceOf(Migration::class, $ownershipMigration);
        $this->ownershipMigration = $ownershipMigration;
        $ownershipMigration->up();

        $this->repository = new PostgresCommercialOfferRepository(
            $this->connection,
            new CommercialOfferPersistenceMapper,
        );
    }

    protected function tearDown(): void
    {
        if ($this->ownershipMigration !== null) {
            $this->connection?->table('commercial_offer_line_items')->delete();
            $this->connection?->table('commercial_offers')->delete();
            $this->ownershipMigration->down();
        }

        if ($this->provenanceMigration !== null) {
            $this->provenanceMigration->down();
        }

        if ($this->tenantIdMigration !== null) {
            $this->tenantIdMigration->down();
        }

        if ($this->migration !== null) {
            $this->migration->down();
        }

        DB::purge(self::CONNECTION_NAME);
        parent::tearDown();
    }

    public function test_persist_reload_cancel_and_rollback_migration(): void
    {
        $offer = $this->offer();
        $this->repository()->save($offer);

        $reloaded = $this->repository()->find($offer->id);
        self::assertNotNull($reloaded);
        self::assertSame(1, $reloaded->version());
        self::assertSame(1, count($reloaded->checkoutSnapshot->lineItems));

        $reloaded->cancel(new PlatformIdentityReference($this->uuid(2)), $this->time());
        $this->repository()->save($reloaded);

        $cancelled = $this->repository()->find($offer->id);
        self::assertNotNull($cancelled);
        self::assertSame('cancelled', $cancelled->status->value);
        self::assertSame(2, $cancelled->version());
    }

    public function test_newly_created_offer_persists_the_same_tenant_id_and_reload_preserves_it(): void
    {
        $offer = $this->offer();
        $this->repository()->save($offer);

        $row = $this->connection()->table('commercial_offers')->where('id', $offer->id->value)->first();
        self::assertNotNull($row);
        self::assertSame($this->uuid(6), $row->tenant_id);

        $reloaded = $this->repository()->find($offer->id);
        self::assertNotNull($reloaded);
        self::assertSame($this->uuid(6), $reloaded->tenantId?->value);
    }

    public function test_initial_acquisition_persists_clinic_registration_as_owner_without_platform_identity(): void
    {
        $offer = CommercialOffer::prepareForClinicRegistration(
            new CommercialOfferId($this->uuid(40)),
            new ClinicRegistrationReference($this->uuid(41)),
            null,
            $this->offer()->checkoutSnapshot,
            OfferExpiry::fromPreparedAt($this->time(), 30),
            $this->time(),
            $this->uuid(43),
        );

        $this->repository()->save($offer);

        $row = $this->connection()->table('commercial_offers')->where('id', $offer->id->value)->first();
        self::assertNotNull($row);
        self::assertNull($row->platform_identity_id);
        self::assertSame('clinic_registration', $row->owner_kind);
        self::assertSame('initial_checkout', $row->purpose);
        self::assertNull($row->tenant_id);

        $reloaded = $this->repository()->findCurrentForClinicRegistration(
            new ClinicRegistrationReference($this->uuid(41)),
        );
        self::assertNotNull($reloaded);
        self::assertNull($reloaded->platformIdentity);
        self::assertSame($offer->id->value, $reloaded->id->value);
    }

    public function test_tenant_id_column_is_nullable_and_legacy_rows_are_not_backfilled(): void
    {
        $legacyId = $this->uuid(30);
        $this->connection()->table('commercial_offers')->insert([
            'id' => $legacyId,
            'platform_identity_id' => $this->uuid(31),
            'clinic_registration_id' => $this->uuid(32),
            'tenant_id' => null,
            'status' => 'prepared',
            'plan_offering_id' => 'offering-basic-monthly',
            'plan_id' => 'plan-basic',
            'billing_cycle_id' => 'monthly',
            'billing_period_start' => '2026-07-21',
            'billing_period_end' => '2026-08-20',
            'offering_configuration_version' => 'catalogue-v1',
            'capability_configuration_reference' => 'capability-v1',
            'subtotal_amount_minor' => 3000,
            'total_amount_minor' => 3000,
            'currency' => 'MYR',
            'expires_at' => $this->time('+30 minutes')->format('Y-m-d H:i:s.uP'),
            'correlation_id' => $this->uuid(33),
            'version' => 1,
            'created_at' => $this->time()->format('Y-m-d H:i:s.uP'),
            'updated_at' => $this->time()->format('Y-m-d H:i:s.uP'),
        ]);
        $this->connection()->table('commercial_offer_line_items')->insert([
            'id' => $this->uuid(34),
            'commercial_offer_id' => $legacyId,
            'item_type' => 'plan_offering',
            'item_reference' => 'offering-basic-monthly',
            'description' => 'Basic — Monthly',
            'quantity' => 1,
            'unit_amount_minor' => 3000,
            'total_amount_minor' => 3000,
            'currency' => 'MYR',
            'catalogue_snapshot_reference' => 'catalogue-v1',
            'position' => 0,
            'created_at' => $this->time()->format('Y-m-d H:i:s.uP'),
            'updated_at' => $this->time()->format('Y-m-d H:i:s.uP'),
        ]);

        $other = $this->offer();
        $this->repository()->save($other);

        $legacyRow = $this->connection()->table('commercial_offers')->where('id', $legacyId)->first();
        self::assertNotNull($legacyRow);
        self::assertNull($legacyRow->tenant_id);

        $legacy = $this->repository()->find(new CommercialOfferId($legacyId));
        self::assertNotNull($legacy);
        self::assertNull($legacy->tenantId);
    }

    public function test_database_rejects_duplicate_prepared_offer_for_platform_identity(): void
    {
        $this->repository()->save($this->offer());

        $this->expectException(QueryException::class);
        $this->repository()->save($this->offer(id: 9));
    }

    public function test_terminal_offer_does_not_block_new_prepared_offer(): void
    {
        $offer = $this->offer();
        $this->repository()->save($offer);
        $offer->cancel(new PlatformIdentityReference($this->uuid(2)), $this->time());
        $this->repository()->save($offer);

        $newOffer = $this->offer(id: 9);
        $this->repository()->save($newOffer);

        self::assertSame($this->uuid(9), $this->repository()->findCurrentForPlatformIdentity(new PlatformIdentityReference($this->uuid(2)))?->id->value);
    }

    public function test_optimistic_locking_rejects_stale_write(): void
    {
        $offer = $this->offer();
        $this->repository()->save($offer);
        $firstCopy = $this->repository()->find($offer->id);
        $staleCopy = $this->repository()->find($offer->id);
        self::assertNotNull($firstCopy);
        self::assertNotNull($staleCopy);

        $firstCopy->cancel(new PlatformIdentityReference($this->uuid(2)), $this->time());
        $this->repository()->save($firstCopy);
        $staleCopy->cancel(new PlatformIdentityReference($this->uuid(2)), $this->time());

        $this->expectException(StaleCommercialOfferWriteException::class);
        $this->repository()->save($staleCopy);
    }

    public function test_line_item_failure_rolls_back_offer(): void
    {
        $this->connection()->unprepared(<<<'SQL'
            CREATE FUNCTION syifa_test_reject_commercial_offer_line_item() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'forced line item failure';
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER syifa_test_reject_commercial_offer_line_item_trigger
            BEFORE INSERT ON commercial_offer_line_items
            FOR EACH ROW EXECUTE FUNCTION syifa_test_reject_commercial_offer_line_item();
            SQL);

        try {
            $offer = $this->offer(id: 20);
            try {
                $this->repository()->save($offer);
                self::fail('Forced line-item failure should abort Commercial Offer save.');
            } catch (Throwable) {
                self::assertFalse($this->connection()->table('commercial_offers')->where('id', $this->uuid(20))->exists());
            }
        } finally {
            $this->connection()->unprepared('DROP TRIGGER IF EXISTS syifa_test_reject_commercial_offer_line_item_trigger ON commercial_offer_line_items');
            $this->connection()->unprepared('DROP FUNCTION IF EXISTS syifa_test_reject_commercial_offer_line_item()');
        }
    }

    private function offer(int $id = 1): CommercialOffer
    {
        return CommercialOffer::prepare(
            new CommercialOfferId($this->uuid($id)),
            new PlatformIdentityReference($this->uuid(2)),
            new ClinicRegistrationReference($this->uuid(3)),
            new TenantId($this->uuid(6)),
            new CheckoutSnapshot(
                'offering-basic-monthly',
                'plan-basic',
                'monthly',
                '2026-07-21',
                '2026-08-20',
                'catalogue-v1',
                'capability-v1',
                [new CommercialOfferLineItem(
                    'plan_offering',
                    'offering-basic-monthly',
                    'Basic — Monthly',
                    1,
                    new PriceSnapshot(3000, 'MYR'),
                    new PriceSnapshot(3000, 'MYR'),
                    'catalogue-v1',
                )],
                new PriceSnapshot(3000, 'MYR'),
                new PriceSnapshot(3000, 'MYR'),
            ),
            OfferExpiry::fromPreparedAt($this->time(), 30),
            $this->time(),
            $this->uuid(4),
        );
    }

    private function repository(): PostgresCommercialOfferRepository
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
        $time = new DateTimeImmutable('2026-07-21T00:00:00Z');

        return $modifier === '' ? $time : $time->modify($modifier);
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
