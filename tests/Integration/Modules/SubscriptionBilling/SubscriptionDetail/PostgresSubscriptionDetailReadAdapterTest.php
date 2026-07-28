<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\SubscriptionBilling\SubscriptionDetail;

use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Queries\PostgresSubscriptionDetailReadAdapter;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PostgresSubscriptionDetailReadAdapterTest extends TestCase
{
    private ?ConnectionInterface $connection = null;

    protected function setUp(): void
    {
        parent::setUp();
        $dsn = getenv('SUBSCRIPTION_BILLING_POSTGRES_TEST_DSN');
        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Requires SUBSCRIPTION_BILLING_POSTGRES_TEST_DSN.');
        }
        config()->set('database.connections.subscription_detail_test', [
            'driver' => 'pgsql', 'url' => $dsn, 'charset' => 'utf8',
            'prefix' => '', 'prefix_indexes' => true, 'search_path' => 'public', 'sslmode' => 'prefer',
        ]);
        DB::purge('subscription_detail_test');
        $this->connection = DB::connection('subscription_detail_test');
        $this->connection()->statement('CREATE TEMP TABLE subscriptions (id text, tenant_id text, clinic_registration_id text, payment_id text, plan_id text, billing_cycle_id text, amount_minor bigint, currency text, starts_on date, ends_on date, status text, auto_renew_status text, version integer)');
        $this->connection()->statement('CREATE TEMP TABLE payments (id text, amount_minor bigint, currency text, status text, domain_last_changed_at timestamptz)');
        $this->connection()->statement('CREATE TEMP TABLE subscription_timeline_entries (id text, subscription_id text, event_type text, occurred_at timestamptz)');
        $this->connection()->statement('CREATE TEMP TABLE subscription_renewals (id text, subscription_id text, commercial_offer_id text, payment_id text, status text, requested_at timestamptz)');
        $this->connection()->statement('CREATE TEMP TABLE commercial_offers (id text, expires_at timestamptz)');
        $this->connection()->statement('CREATE TEMP TABLE commercial_catalogue_plans (id uuid, name text)');
        $this->connection()->statement('CREATE TEMP TABLE commercial_catalogue_billing_options (id uuid, name text)');
    }

    protected function tearDown(): void
    {
        DB::purge('subscription_detail_test');
        parent::tearDown();
    }

    public function test_it_reads_detail_and_initial_payment_without_fabricating_timeline_or_auto_renew(): void
    {
        $this->connection()->table('payments')->insert(['id' => 'pay-1', 'amount_minor' => 120000, 'currency' => 'MYR', 'status' => 'succeeded', 'domain_last_changed_at' => '2026-01-01 00:00:00+00']);
        $this->connection()->table('subscriptions')->insert(['id' => 'sub-1', 'tenant_id' => 'tenant-1', 'clinic_registration_id' => 'registration-1', 'payment_id' => 'pay-1', 'plan_id' => 'essential', 'billing_cycle_id' => 'annual', 'amount_minor' => 120000, 'currency' => 'MYR', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'renewal_due', 'auto_renew_status' => 'disabled', 'version' => 2]);
        $adapter = new PostgresSubscriptionDetailReadAdapter($this->connection());

        $detail = $adapter->detail('sub-1');
        self::assertNotNull($detail);
        self::assertSame('due', $detail->renewalStatus);
        self::assertSame('disabled', $detail->autoRenewStatus);
        self::assertNull($detail->renewalCheckoutId);
        self::assertSame([], $adapter->list('sub-1', null, 20));
        $payments = $adapter->listForSubscription('sub-1', null, 20);
        self::assertCount(1, $payments);
        self::assertSame('initial_activation', $payments[0]->purpose);
    }

    public function test_it_exposes_only_an_eligible_requested_renewal_for_checkout(): void
    {
        $this->connection()->table('subscriptions')->insert([
            'id' => 'sub-1', 'tenant_id' => 'tenant-1', 'clinic_registration_id' => 'registration-1',
            'payment_id' => 'pay-1', 'plan_id' => 'essential', 'billing_cycle_id' => 'annual',
            'amount_minor' => 120000, 'currency' => 'MYR', 'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31', 'status' => 'renewal_due', 'auto_renew_status' => 'disabled',
            'version' => 2,
        ]);
        $this->connection()->table('commercial_offers')->insert([
            ['id' => 'offer-valid', 'expires_at' => now()->addHour()],
            ['id' => 'offer-expired', 'expires_at' => now()->subHour()],
        ]);
        $this->connection()->table('subscription_renewals')->insert([
            [
                'id' => 'renewal-eligible', 'subscription_id' => 'sub-1',
                'commercial_offer_id' => 'offer-valid', 'payment_id' => 'pay-renewal',
                'status' => 'requested', 'requested_at' => now(),
            ],
            [
                'id' => 'renewal-expired', 'subscription_id' => 'sub-1',
                'commercial_offer_id' => 'offer-expired', 'payment_id' => 'pay-expired',
                'status' => 'requested', 'requested_at' => now()->subMinute(),
            ],
        ]);

        $detail = (new PostgresSubscriptionDetailReadAdapter($this->connection()))->detail('sub-1');

        self::assertNotNull($detail);
        self::assertSame('renewal-eligible', $detail->renewalCheckoutId);
    }

    public function test_it_reads_clinic_owner_detail_exclusively_by_trusted_tenant(): void
    {
        $planId = '00000000-0000-4000-8000-000000000101';
        $billingCycleId = '00000000-0000-4000-8000-000000000102';
        $this->connection()->table('commercial_catalogue_plans')->insert([
            'id' => $planId, 'name' => 'Syifa Essential',
        ]);
        $this->connection()->table('commercial_catalogue_billing_options')->insert([
            'id' => $billingCycleId, 'name' => 'Annual',
        ]);
        $this->connection()->table('payments')->insert([
            'id' => 'pay-1', 'amount_minor' => 120000, 'currency' => 'MYR',
            'status' => 'succeeded', 'domain_last_changed_at' => '2026-01-01 00:00:00+00',
        ]);
        $this->connection()->table('subscriptions')->insert([
            'id' => 'sub-1', 'tenant_id' => 'tenant-1', 'clinic_registration_id' => 'registration-1',
            'payment_id' => 'pay-1', 'plan_id' => $planId, 'billing_cycle_id' => $billingCycleId,
            'amount_minor' => 120000, 'currency' => 'MYR', 'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31', 'status' => 'active', 'auto_renew_status' => 'disabled',
            'version' => 2,
        ]);

        $adapter = new PostgresSubscriptionDetailReadAdapter($this->connection());
        $detail = $adapter->detailForTenant('tenant-1');

        self::assertNotNull($detail);
        self::assertSame('Syifa Essential', $detail->planName);
        self::assertSame('Annual', $detail->billingCycleName);
        self::assertSame('succeeded', $detail->latestPaymentStatus);
        self::assertFalse($detail->renewalEligible);
        self::assertNull($adapter->detailForTenant('tenant-2'));
    }

    private function connection(): ConnectionInterface
    {
        self::assertNotNull($this->connection);

        return $this->connection;
    }
}
