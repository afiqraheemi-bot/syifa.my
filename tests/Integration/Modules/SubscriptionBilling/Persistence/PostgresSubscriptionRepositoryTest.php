<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\SubscriptionBilling\Persistence;

use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\Subscription;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\BillingCycleId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\BillingPeriod;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\CapabilityKey;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\ClinicRegistrationId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\CommercialOfferId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\Entitlement;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\EntitlementStatus;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\Money;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\PaymentId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\PlanId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\SubscriptionId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\TenantId;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\SubscriptionPersistenceMapper;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresSubscriptionRepository;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PostgresSubscriptionRepositoryTest extends TestCase
{
    private const string CONNECTION = 'subscription_postgres_integration';

    private ?ConnectionInterface $connection = null;

    private ?Migration $migration = null;

    private ?PostgresSubscriptionRepository $repository = null;

    protected function setUp(): void
    {
        parent::setUp();
        $dsn = getenv('SUBSCRIPTION_BILLING_POSTGRES_TEST_DSN');
        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Requires SUBSCRIPTION_BILLING_POSTGRES_TEST_DSN for a dedicated disposable PostgreSQL database.');
        }
        config()->set('database.default', self::CONNECTION);
        config()->set('database.connections.'.self::CONNECTION, [
            'driver' => 'pgsql', 'url' => $dsn, 'charset' => 'utf8', 'prefix' => '',
            'prefix_indexes' => true, 'search_path' => 'public', 'sslmode' => 'prefer',
        ]);
        DB::purge(self::CONNECTION);
        $this->connection = DB::connection(self::CONNECTION);
        Schema::connection(self::CONNECTION)->dropIfExists('subscriptions');
        $migration = require base_path('database/migrations/subscription_billing/2026_07_27_000001_create_subscriptions_table.php');
        self::assertInstanceOf(Migration::class, $migration);
        $this->migration = $migration;
        $migration->up();
        $this->repository = new PostgresSubscriptionRepository($this->connection, new SubscriptionPersistenceMapper);
    }

    protected function tearDown(): void
    {
        $this->migration?->down();
        DB::purge(self::CONNECTION);
        parent::tearDown();
    }

    public function test_save_load_and_round_trip_every_owned_value(): void
    {
        $subscription = $this->subscription();
        $this->repository()->save($subscription);
        self::assertSame(1, $subscription->version());

        $loaded = $this->repository()->find($subscription->id);
        self::assertNotNull($loaded);
        self::assertSame($subscription->tenantId->value, $loaded->tenantId->value);
        self::assertSame($subscription->clinicRegistrationId->value, $loaded->clinicRegistrationId->value);
        self::assertSame($subscription->paymentId->value, $loaded->paymentId->value);
        self::assertSame($subscription->commercialOfferId->value, $loaded->commercialOfferId->value);
        self::assertSame($subscription->billingPeriod()->startsOn, $loaded->billingPeriod()->startsOn);
        self::assertSame($subscription->entitlement()->capabilities[0]->value, $loaded->entitlement()->capabilities[0]->value);
        self::assertSame($subscription->id->value, $this->repository()->findByTenantId($subscription->tenantId)?->id->value);
        self::assertSame($subscription->id->value, $this->repository()->findByPaymentId($subscription->paymentId)?->id->value);
    }

    public function test_database_enforces_tenant_uniqueness(): void
    {
        $this->repository()->save($this->subscription());
        $this->expectException(QueryException::class);
        $this->repository()->save($this->subscription(id: 20, payment: 21));
    }

    public function test_database_enforces_payment_uniqueness(): void
    {
        $this->repository()->save($this->subscription());
        $this->expectException(QueryException::class);
        $this->repository()->save($this->subscription(id: 20, tenant: 22));
    }

    public function test_migration_is_reversible(): void
    {
        self::assertTrue(Schema::connection(self::CONNECTION)->hasTable('subscriptions'));
        $this->migration?->down();
        $this->migration = null;
        self::assertFalse(Schema::connection(self::CONNECTION)->hasTable('subscriptions'));
    }

    private function subscription(int $id = 1, int $payment = 4, int $tenant = 2): Subscription
    {
        $plan = new PlanId($this->uuid(6));
        $cycle = new BillingCycleId($this->uuid(7));

        return Subscription::create(
            new SubscriptionId($this->uuid($id)), new TenantId($this->uuid($tenant)),
            new ClinicRegistrationId($this->uuid(3)), new PaymentId($this->uuid($payment)),
            new CommercialOfferId($this->uuid(5)), $plan, $cycle, new Money(12500, 'MYR'),
            new BillingPeriod('2026-07-22', '2027-07-21'),
            new Entitlement($plan, $cycle, 'catalogue-v1', EntitlementStatus::Pending, [new CapabilityKey('appointments.manage')]),
            new DateTimeImmutable('2026-07-22T00:00:00Z'),
        );
    }

    private function repository(): PostgresSubscriptionRepository
    {
        self::assertNotNull($this->repository);

        return $this->repository;
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
