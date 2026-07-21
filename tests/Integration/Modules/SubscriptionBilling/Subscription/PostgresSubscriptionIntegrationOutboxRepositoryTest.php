<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\SubscriptionBilling\Subscription;

use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivatedIntegrationEvent;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\SubscriptionIntegrationOutboxPersistenceMapper;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresSubscriptionIntegrationOutboxRepository;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PostgresSubscriptionIntegrationOutboxRepositoryTest extends TestCase
{
    private const string CONNECTION = 'subscription_outbox_postgres';

    private ?ConnectionInterface $connection = null;

    /** @var list<Migration> */
    private array $migrations = [];

    private ?PostgresSubscriptionIntegrationOutboxRepository $repository = null;

    protected function setUp(): void
    {
        parent::setUp();
        $dsn = getenv('SUBSCRIPTION_BILLING_POSTGRES_TEST_DSN');
        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Requires disposable PostgreSQL.');
        }
        config()->set('database.default', self::CONNECTION);
        config()->set('database.connections.'.self::CONNECTION, ['driver' => 'pgsql', 'url' => $dsn, 'charset' => 'utf8', 'prefix' => '', 'prefix_indexes' => true, 'search_path' => 'public', 'sslmode' => 'prefer']);
        DB::purge(self::CONNECTION);
        $this->connection = DB::connection(self::CONNECTION);
        Schema::connection(self::CONNECTION)->dropIfExists('subscription_integration_outbox');
        Schema::connection(self::CONNECTION)->dropIfExists('subscriptions');
        foreach (['2026_07_27_000001_create_subscriptions_table.php', '2026_07_29_000001_create_subscription_integration_outbox.php'] as $file) {
            $migration = require base_path('database/migrations/subscription_billing/'.$file);
            self::assertInstanceOf(Migration::class, $migration);
            $migration->up();
            $this->migrations[] = $migration;
        }
        $this->insertSubscription();
        $this->repository = new PostgresSubscriptionIntegrationOutboxRepository($this->connection, new SubscriptionIntegrationOutboxPersistenceMapper);
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->migrations) as $migration) {
            $migration->down();
        }
        DB::purge(self::CONNECTION);
        parent::tearDown();
    }

    public function test_idempotent_persistence_pending_lookup_and_lease_acquisition(): void
    {
        $event = $this->event();
        $this->repository()->add($event);
        $this->repository()->add($event);
        self::assertSame(1, $this->connection()->table('subscription_integration_outbox')->count());
        self::assertCount(1, $this->repository()->pending($this->time()));
        $claim = $this->repository()->claimNext($this->time());
        self::assertNotNull($claim);
        self::assertSame(1, $claim->attemptCount);
        self::assertNotSame('', $claim->leaseToken);
        self::assertNull($this->repository()->claimNext($this->time()->modify('+1 minute')));
        $reclaimed = $this->repository()->claimNext($this->time()->modify('+3 minutes'));
        self::assertNotNull($reclaimed);
        self::assertSame(2, $reclaimed->attemptCount);
        self::assertNotSame($claim->leaseToken, $reclaimed->leaseToken);
    }

    public function test_retry_metadata_and_dispatch_completion_require_current_lease(): void
    {
        $this->repository()->add($this->event());
        $claim = $this->repository()->claimNext($this->time());
        self::assertNotNull($claim);
        $retryAt = $this->time()->modify('+30 seconds');
        self::assertTrue($this->repository()->releaseForRetry($claim->event->eventId, $claim->leaseToken, $retryAt, 'delivery_failed', $this->time()));
        $row = $this->connection()->table('subscription_integration_outbox')->first();
        self::assertSame('delivery_failed', $row->safe_failure_label);
        self::assertNotNull($row->next_publish_attempt_at);
        self::assertNull($this->repository()->claimNext($this->time()));
        $next = $this->repository()->claimNext($retryAt);
        self::assertNotNull($next);
        self::assertFalse($this->repository()->completeDispatch($next->event->eventId, $claim->leaseToken, $retryAt));
        self::assertTrue($this->repository()->completeDispatch($next->event->eventId, $next->leaseToken, $retryAt));
        $row = $this->connection()->table('subscription_integration_outbox')->first();
        self::assertNotNull($row->published_at);
        self::assertNull($row->publish_claim_token);
        self::assertNull($row->next_publish_attempt_at);
    }

    private function insertSubscription(): void
    {
        $timestamp = $this->time()->format('Y-m-d H:i:s.uP');
        $this->connection()->table('subscriptions')->insert([
            'id' => $this->uuid(2), 'tenant_id' => $this->uuid(3), 'clinic_registration_id' => $this->uuid(4), 'payment_id' => $this->uuid(5), 'commercial_offer_id' => $this->uuid(6),
            'plan_id' => $this->uuid(7), 'billing_cycle_id' => $this->uuid(8), 'amount_minor' => 12500, 'currency' => 'MYR', 'starts_on' => '2026-07-25', 'ends_on' => '2027-07-24',
            'status' => 'active', 'entitlement_configuration_version' => 'v1', 'entitlement_status' => 'effective', 'entitlement_capabilities' => '[]',
            'created_at_domain' => $timestamp, 'last_changed_at' => $timestamp, 'version' => 1, 'created_at' => $timestamp, 'updated_at' => $timestamp,
        ]);
    }

    private function event(): SubscriptionActivatedIntegrationEvent
    {
        return new SubscriptionActivatedIntegrationEvent($this->uuid(1), $this->uuid(2), $this->uuid(3), $this->uuid(4), $this->uuid(5), $this->uuid(6), $this->uuid(7), $this->uuid(8), '2026-07-25', '2027-07-24', $this->time());
    }

    private function repository(): PostgresSubscriptionIntegrationOutboxRepository
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
        return new DateTimeImmutable('2026-07-25T00:00:00Z');
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
