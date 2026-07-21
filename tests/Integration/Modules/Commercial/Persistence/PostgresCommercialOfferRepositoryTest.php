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

        $this->repository = new PostgresCommercialOfferRepository(
            $this->connection,
            new CommercialOfferPersistenceMapper,
        );
    }

    protected function tearDown(): void
    {
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

    private function time(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-21T00:00:00Z');
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
