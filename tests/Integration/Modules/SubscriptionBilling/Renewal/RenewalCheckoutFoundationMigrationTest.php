<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\SubscriptionBilling\Renewal;

use App\Modules\SubscriptionBilling\Contracts\Renewal\ApplyRenewalOutcomeCommand;
use App\Modules\SubscriptionBilling\Contracts\Renewal\BeginRenewalCheckoutCommand;
use App\Modules\SubscriptionBilling\Contracts\Renewal\ExpiryAuthority;
use App\Modules\SubscriptionBilling\Contracts\Renewal\PaymentSession;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RedirectAction;
use App\Modules\SubscriptionBilling\Infrastructure\Subscription\PostgresRenewalCheckoutStore;
use App\Modules\SubscriptionBilling\Infrastructure\Subscription\PostgresRenewalOutcomeStore;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class RenewalCheckoutFoundationMigrationTest extends TestCase
{
    private ?ConnectionInterface $connection = null;

    protected function setUp(): void
    {
        parent::setUp();
        $dsn = getenv('SUBSCRIPTION_BILLING_POSTGRES_TEST_DSN');
        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Requires SUBSCRIPTION_BILLING_POSTGRES_TEST_DSN.');
        }
        config()->set('database.default', 'renewal_checkout_foundation_test');
        config()->set('database.connections.renewal_checkout_foundation_test', [
            'driver' => 'pgsql', 'url' => $dsn, 'charset' => 'utf8',
            'prefix' => '', 'prefix_indexes' => true, 'search_path' => 'public', 'sslmode' => 'prefer',
        ]);
        DB::purge('renewal_checkout_foundation_test');
        $this->connection = DB::connection('renewal_checkout_foundation_test');
        $this->db()->statement('DROP SCHEMA IF EXISTS renewal_checkout_foundation_test CASCADE');
        $this->db()->statement('CREATE SCHEMA renewal_checkout_foundation_test');
        $this->db()->statement('SET search_path TO renewal_checkout_foundation_test');
        $this->db()->statement('CREATE TABLE subscriptions (id uuid PRIMARY KEY, ends_on date, status text, auto_renew_status text, auto_renew_changed_at timestamptz, last_changed_at timestamptz, version bigint, updated_at timestamptz)');
        $this->db()->statement('CREATE TABLE payments (id uuid PRIMARY KEY, status text)');
        $this->db()->statement('CREATE TABLE subscription_renewals (id uuid PRIMARY KEY, subscription_id uuid NOT NULL REFERENCES subscriptions(id), commercial_offer_id uuid, request_idempotency_key text, mode text, status text, starts_on date, ends_on date, last_changed_at timestamptz, version bigint, updated_at timestamptz)');
        $this->db()->statement('CREATE TABLE subscription_timeline_entries (id uuid PRIMARY KEY, subscription_id uuid, renewal_id uuid, event_type text, actor_id uuid, correlation_id uuid, occurred_at timestamptz)');
        $this->migration()->up();
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            $this->migration()->down();
            $this->db()->statement('DROP SCHEMA IF EXISTS renewal_checkout_foundation_test CASCADE');
        }
        DB::purge('renewal_checkout_foundation_test');
        parent::tearDown();
    }

    public function test_migration_adds_immutable_payment_lineage_and_durable_foundation_tables(): void
    {
        self::assertTrue(Schema::hasColumn('subscription_renewals', 'payment_id'));
        self::assertTrue(Schema::hasColumn('subscription_timeline_entries', 'payment_id'));
        self::assertTrue(Schema::hasTable('renewal_checkout_applications'));
        self::assertTrue(Schema::hasTable('renewal_outcome_applications'));
        self::assertTrue(Schema::hasTable('subscription_renewal_outbox'));

        $subscriptionId = '11111111-1111-4111-8111-111111111111';
        $renewalId = '22222222-2222-4222-8222-222222222222';
        $paymentId = '33333333-3333-4333-8333-333333333333';
        $this->db()->table('subscriptions')->insert(['id' => $subscriptionId]);
        $this->db()->table('payments')->insert(['id' => $paymentId]);
        $this->db()->table('subscription_renewals')->insert([
            'id' => $renewalId, 'subscription_id' => $subscriptionId, 'payment_id' => $paymentId,
        ]);
        $this->db()->table('renewal_checkout_applications')->insert([
            'id' => '44444444-4444-4444-8444-444444444444',
            'renewal_id' => $renewalId,
            'payment_id' => $paymentId,
            'idempotency_key' => 'checkout-key',
            'stage' => 'session_pending',
            'commercial_offer_valid_until' => '2027-01-01 00:00:00+00',
            'started_at' => '2026-12-01 00:00:00+00',
            'created_at' => '2026-12-01 00:00:00+00',
            'updated_at' => '2026-12-01 00:00:00+00',
        ]);

        self::assertSame($paymentId, $this->db()->table('subscription_renewals')->value('payment_id'));
        self::assertSame('session_pending', $this->db()->table('renewal_checkout_applications')->value('stage'));
    }

    public function test_checkout_and_authoritative_success_update_lineage_timeline_and_outbox_atomically(): void
    {
        $subscriptionId = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
        $renewalId = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
        $paymentId = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
        $correlationId = 'dddddddd-dddd-4ddd-8ddd-dddddddddddd';
        $this->db()->table('subscriptions')->insert([
            'id' => $subscriptionId, 'ends_on' => '2026-12-31', 'status' => 'renewal_due',
            'auto_renew_status' => 'disabled', 'version' => 2,
        ]);
        $this->db()->table('payments')->insert(['id' => $paymentId, 'status' => 'pending']);
        $this->db()->table('subscription_renewals')->insert([
            'id' => $renewalId, 'subscription_id' => $subscriptionId,
            'commercial_offer_id' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
            'request_idempotency_key' => 'renewal-key', 'mode' => 'manual', 'status' => 'requested',
            'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31', 'version' => 1,
        ]);
        $checkout = new PostgresRenewalCheckoutStore($this->db());
        $state = $checkout->begin(new BeginRenewalCheckoutCommand(
            $renewalId, $paymentId, new DateTimeImmutable('2027-01-01T00:00:00Z'),
            'checkout-key-2', $correlationId, new DateTimeImmutable('2026-12-01T00:00:00Z'),
        ));
        $checkout->sessionReady($state->applicationId, $paymentId, new PaymentSession(
            'session-2', new RedirectAction('https://checkout.example.test/session-2'),
            new DateTimeImmutable('2027-01-01T00:00:00Z'), ExpiryAuthority::Provider,
        ), $correlationId);
        self::assertSame('payment_pending', $this->db()->table('subscription_renewals')->where('id', $renewalId)->value('status'));

        $this->db()->table('payments')->where('id', $paymentId)->update(['status' => 'succeeded']);
        $result = (new PostgresRenewalOutcomeStore($this->db()))->apply(new ApplyRenewalOutcomeCommand(
            'ffffffff-ffff-4fff-8fff-ffffffffffff', $paymentId, 'succeeded',
            $correlationId, new DateTimeImmutable('2026-12-02T00:00:00Z'),
        ));

        self::assertSame('applied', $result->code);
        self::assertSame('2027-12-31', $this->db()->table('subscriptions')->where('id', $subscriptionId)->value('ends_on'));
        self::assertSame('succeeded', $this->db()->table('subscription_renewals')->where('id', $renewalId)->value('status'));
        self::assertSame(2, $this->db()->table('subscription_timeline_entries')->count());
        self::assertSame(2, $this->db()->table('subscription_renewal_outbox')->count());
    }

    public function test_an_initial_acquisition_payment_is_ignored_as_renewal_missing_without_touching_any_subscription(): void
    {
        $subscriptionId = '99999999-9999-4999-8999-999999999999';
        $paymentId = '88888888-8888-4888-8888-888888888888';
        $this->db()->table('subscriptions')->insert(['id' => $subscriptionId, 'status' => 'active', 'version' => 1]);
        // Deliberately no subscription_renewals row for this payment — this
        // is what an initial-acquisition VerifiedPaymentSucceeded looks like
        // to the renewal outcome store: a payment id it has never seen.
        $this->db()->table('payments')->insert(['id' => $paymentId, 'status' => 'succeeded']);

        $result = (new PostgresRenewalOutcomeStore($this->db()))->apply(new ApplyRenewalOutcomeCommand(
            'aaaaaaaa-1111-4111-8111-111111111111', $paymentId, 'succeeded',
            'correlation-initial-acquisition', new DateTimeImmutable('2026-12-02T00:00:00Z'),
        ));

        self::assertSame('renewal_missing', $result->code);
        self::assertSame('active', $this->db()->table('subscriptions')->where('id', $subscriptionId)->value('status'));
        self::assertSame(1, $this->db()->table('subscriptions')->count());
        self::assertSame(0, $this->db()->table('subscription_timeline_entries')->count());
        self::assertSame(0, $this->db()->table('subscription_renewal_outbox')->count());
    }

    private function migration(): object
    {
        return require base_path('database/migrations/subscription_billing/2026_07_31_000001_create_renewal_checkout_foundation.php');
    }

    private function db(): ConnectionInterface
    {
        self::assertNotNull($this->connection);

        return $this->connection;
    }
}
