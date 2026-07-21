<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\SubscriptionBilling\Payment;

use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Payment;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\IdempotencyKey;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentAmount;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentCurrency;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentReference;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\ProviderReference;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\TenantId;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Exceptions\StalePaymentWriteException;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\PaymentPersistenceMapper;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresPaymentRepository;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PostgresPaymentRepositoryTest extends TestCase
{
    private const string CONNECTION_NAME = 'payment_postgres_integration';

    private ?ConnectionInterface $connection = null;

    private ?PostgresPaymentRepository $repository = null;

    private ?Migration $migration = null;

    private ?Migration $tenantIdMigration = null;

    protected function setUp(): void
    {
        parent::setUp();

        $dsn = getenv('SUBSCRIPTION_BILLING_POSTGRES_TEST_DSN');

        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Requires SUBSCRIPTION_BILLING_POSTGRES_TEST_DSN for a dedicated disposable PostgreSQL database.');
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
        Schema::connection(self::CONNECTION_NAME)->dropIfExists('payment_attempts');
        Schema::connection(self::CONNECTION_NAME)->dropIfExists('payments');

        $migration = require base_path('database/migrations/subscription_billing/2026_07_21_000002_create_payment_core_tables.php');
        self::assertInstanceOf(Migration::class, $migration);
        $this->migration = $migration;
        $migration->up();

        $tenantIdMigration = require base_path('database/migrations/subscription_billing/2026_07_26_000001_add_tenant_id_to_payments.php');
        self::assertInstanceOf(Migration::class, $tenantIdMigration);
        $this->tenantIdMigration = $tenantIdMigration;
        $tenantIdMigration->up();

        $this->repository = new PostgresPaymentRepository($this->connection, new PaymentPersistenceMapper);
    }

    protected function tearDown(): void
    {
        if ($this->tenantIdMigration !== null) {
            $this->tenantIdMigration->down();
        }

        if ($this->migration !== null) {
            $this->migration->down();
        }

        DB::purge(self::CONNECTION_NAME);
        parent::tearDown();
    }

    public function test_persist_reload_transition_and_optimistic_locking(): void
    {
        $payment = $this->payment();
        $this->repository()->save($payment);

        $reloaded = $this->repository()->find($payment->id);
        self::assertNotNull($reloaded);
        self::assertSame(1, $reloaded->version());
        self::assertSame('draft', $reloaded->status->value);

        $firstCopy = $this->repository()->find($payment->id);
        $staleCopy = $this->repository()->find($payment->id);
        self::assertNotNull($firstCopy);
        self::assertNotNull($staleCopy);

        $firstCopy->start($this->uuid(9), 'provider-neutral', $this->time());
        $firstCopy->markPending(new ProviderReference('provider-neutral', 'provider-payment-1'), $this->time());
        $this->repository()->save($firstCopy);

        $staleCopy->markFailed('declined', $this->time());
        $this->expectException(StalePaymentWriteException::class);
        $this->repository()->save($staleCopy);
    }

    public function test_idempotency_and_provider_reference_lookup(): void
    {
        $payment = $this->payment();
        $payment->start($this->uuid(9), 'provider-neutral', $this->time());
        $payment->markPending(new ProviderReference('provider-neutral', 'provider-payment-1'), $this->time());
        $this->repository()->save($payment);

        self::assertSame($payment->id->value, $this->repository()->findByIdempotencyKey(new IdempotencyKey('idem-1'))?->id->value);
        self::assertSame($payment->id->value, $this->repository()->findByProviderReference(new ProviderReference('provider-neutral', 'provider-payment-1'))?->id->value);
    }

    public function test_database_rejects_second_payment_for_same_commercial_offer(): void
    {
        $this->repository()->save($this->payment());

        $this->expectException(QueryException::class);
        $this->repository()->save(Payment::create(
            new PaymentId($this->uuid(90)),
            new PaymentReference($this->uuid(2)),
            new PaymentReference($this->uuid(3)),
            new PaymentReference($this->uuid(4)),
            new TenantId($this->uuid(6)),
            new PaymentAmount(3000),
            new PaymentCurrency('MYR'),
            new IdempotencyKey('idem-90'),
            $this->time(),
        ));
    }

    public function test_newly_created_payment_persists_the_same_tenant_id_and_reload_preserves_it(): void
    {
        $payment = $this->payment();
        $this->repository()->save($payment);

        $row = $this->connection()->table('payments')->where('id', $payment->id->value)->first();
        self::assertNotNull($row);
        self::assertSame($this->uuid(6), $row->tenant_id);

        $reloaded = $this->repository()->find($payment->id);
        self::assertNotNull($reloaded);
        self::assertSame($this->uuid(6), $reloaded->tenantId?->value);
    }

    public function test_tenant_id_column_is_nullable_and_legacy_rows_are_not_backfilled(): void
    {
        $legacyId = $this->uuid(40);
        $this->connection()->table('payments')->insert([
            'id' => $legacyId,
            'commercial_offer_id' => $this->uuid(41),
            'clinic_registration_id' => $this->uuid(42),
            'platform_identity_id' => $this->uuid(43),
            'tenant_id' => null,
            'amount_minor' => 3000,
            'currency' => 'MYR',
            'idempotency_key' => 'idem-legacy',
            'status' => 'draft',
            'domain_created_at' => $this->time()->format('Y-m-d H:i:s.uP'),
            'domain_last_changed_at' => $this->time()->format('Y-m-d H:i:s.uP'),
            'version' => 1,
            'created_at' => $this->time()->format('Y-m-d H:i:s.uP'),
            'updated_at' => $this->time()->format('Y-m-d H:i:s.uP'),
        ]);

        $this->repository()->save($this->payment());

        $legacyRow = $this->connection()->table('payments')->where('id', $legacyId)->first();
        self::assertNotNull($legacyRow);
        self::assertNull($legacyRow->tenant_id);

        $legacy = $this->repository()->find(new PaymentId($legacyId));
        self::assertNotNull($legacy);
        self::assertNull($legacy->tenantId);
    }

    private function payment(): Payment
    {
        return Payment::create(
            new PaymentId($this->uuid(1)),
            new PaymentReference($this->uuid(2)),
            new PaymentReference($this->uuid(3)),
            new PaymentReference($this->uuid(4)),
            new TenantId($this->uuid(6)),
            new PaymentAmount(3000),
            new PaymentCurrency('MYR'),
            new IdempotencyKey('idem-1'),
            $this->time(),
        );
    }

    private function repository(): PostgresPaymentRepository
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
