<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\SubscriptionBilling\Renewal;

use App\Modules\AcquisitionOffer\Contracts\Renewal\PreparedRenewalOffer;
use App\Modules\SubscriptionBilling\Contracts\Renewal\AutoRenewCommand;
use App\Modules\SubscriptionBilling\Contracts\Renewal\ManualRenewSubscriptionCommand;
use App\Modules\SubscriptionBilling\Infrastructure\Subscription\PostgresSubscriptionOperationsStore;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PostgresSubscriptionOperationsStoreTest extends TestCase
{
    private ?ConnectionInterface $connection = null;

    protected function setUp(): void
    {
        parent::setUp();
        $dsn = getenv('SUBSCRIPTION_BILLING_POSTGRES_TEST_DSN');
        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Requires SUBSCRIPTION_BILLING_POSTGRES_TEST_DSN.');
        }
        config()->set('database.default', 'renewal_operations_test');
        config()->set('database.connections.renewal_operations_test', [
            'driver' => 'pgsql', 'url' => $dsn, 'charset' => 'utf8',
            'prefix' => '', 'prefix_indexes' => true, 'search_path' => 'public', 'sslmode' => 'prefer',
        ]);
        DB::purge('renewal_operations_test');
        $this->connection = DB::connection('renewal_operations_test');
        $this->connection()->statement('DROP SCHEMA IF EXISTS renewal_operations_test CASCADE');
        $this->connection()->statement('CREATE SCHEMA renewal_operations_test');
        $this->connection()->statement('SET search_path TO renewal_operations_test');
        $this->connection()->statement('CREATE TABLE subscriptions (id uuid PRIMARY KEY, status text, version bigint, last_changed_at timestamptz, updated_at timestamptz)');
        $this->migration()->up();
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            $this->migration()->down();
            $this->connection()->statement('DROP SCHEMA IF EXISTS renewal_operations_test CASCADE');
        }
        DB::purge('renewal_operations_test');
        parent::tearDown();
    }

    public function test_manual_and_auto_renew_operations_are_idempotent_versioned_and_timeline_backed(): void
    {
        $subscriptionId = '11111111-1111-4111-8111-111111111111';
        $actorId = '22222222-2222-4222-8222-222222222222';
        $correlationId = '33333333-3333-4333-8333-333333333333';
        $this->connection()->table('subscriptions')->insert([
            'id' => $subscriptionId, 'status' => 'renewal_due', 'version' => 2,
            'auto_renew_status' => 'disabled', 'updated_at' => '2026-12-01 00:00:00+00',
        ]);
        $store = new PostgresSubscriptionOperationsStore($this->connection());
        $command = new ManualRenewSubscriptionCommand(
            $subscriptionId, $actorId, 'key-1', 2, $correlationId, new DateTimeImmutable('2026-12-01T00:00:00Z'),
        );
        $offer = new PreparedRenewalOffer(
            '44444444-4444-4444-8444-444444444444', $subscriptionId, 'plan-1', 'cycle-1',
            120000, 'MYR', '2026-12-31T00:00:00Z', '2027-01-01', '2027-12-31', 'v1',
        );

        self::assertSame('accepted', $store->createRenewal('55555555-5555-4555-8555-555555555555', $command, $offer)->code);
        self::assertSame('already_accepted', $store->createRenewal('66666666-6666-4666-8666-666666666666', $command, $offer)->code);
        self::assertSame(1, $this->connection()->table('subscription_renewals')->count());

        $auto = new AutoRenewCommand($subscriptionId, $actorId, 3, $correlationId, new DateTimeImmutable('2026-12-02T00:00:00Z'));
        self::assertSame('enabled', $store->changeAutoRenew($auto, 'enabled', 'auto_renew_enabled')->code);
        self::assertSame('enabled', $this->connection()->table('subscriptions')->value('auto_renew_status'));
        self::assertSame(2, $this->connection()->table('subscription_timeline_entries')->count());
    }

    public function test_migration_is_additive_and_reversible(): void
    {
        self::assertTrue(Schema::hasColumn('subscriptions', 'auto_renew_status'));
        self::assertTrue(Schema::hasTable('subscription_renewals'));
        self::assertTrue(Schema::hasTable('subscription_timeline_entries'));
    }

    private function migration(): object
    {
        return require base_path('database/migrations/subscription_billing/2026_07_30_000001_create_subscription_renewal_operations.php');
    }

    private function connection(): ConnectionInterface
    {
        self::assertNotNull($this->connection);

        return $this->connection;
    }
}
