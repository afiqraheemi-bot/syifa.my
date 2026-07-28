<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\SubscriptionBilling\BillingOverview;

use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Queries\PostgresBillingOverviewReadAdapter;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PostgresBillingOverviewReadAdapterTest extends TestCase
{
    private ?ConnectionInterface $connection = null;

    protected function setUp(): void
    {
        parent::setUp();
        $dsn = getenv('SUBSCRIPTION_BILLING_POSTGRES_TEST_DSN');
        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Requires SUBSCRIPTION_BILLING_POSTGRES_TEST_DSN.');
        }
        config()->set('database.connections.billing_overview_test', [
            'driver' => 'pgsql', 'url' => $dsn, 'charset' => 'utf8',
            'prefix' => '', 'prefix_indexes' => true, 'search_path' => 'public', 'sslmode' => 'prefer',
        ]);
        DB::purge('billing_overview_test');
        $this->connection = DB::connection('billing_overview_test');
        $this->connection()->statement(
            'CREATE TEMP TABLE subscriptions (id text, tenant_id text, plan_id text, billing_cycle_id text, amount_minor bigint, currency text, starts_on date, ends_on date, status text)',
        );
        $this->connection()->statement(
            'CREATE TEMP TABLE payments (id text, tenant_id text, amount_minor bigint, currency text, status text, domain_last_changed_at timestamptz)',
        );
        $this->connection()->statement(
            'CREATE TEMP TABLE payment_reconciliation_cases (id text, status text)',
        );
    }

    protected function tearDown(): void
    {
        DB::purge('billing_overview_test');
        parent::tearDown();
    }

    public function test_it_projects_authoritative_billing_summaries_search_and_cursor_pagination(): void
    {
        $this->connection()->table('subscriptions')->insert([
            ['id' => 'sub-1', 'tenant_id' => 'tenant-1', 'plan_id' => 'essential', 'billing_cycle_id' => 'annual', 'amount_minor' => 120000, 'currency' => 'MYR', 'starts_on' => '2026-01-01', 'ends_on' => '2026-07-31', 'status' => 'active'],
            ['id' => 'sub-2', 'tenant_id' => 'tenant-2', 'plan_id' => 'essential', 'billing_cycle_id' => 'annual', 'amount_minor' => 120000, 'currency' => 'MYR', 'starts_on' => '2025-01-01', 'ends_on' => '2025-12-31', 'status' => 'expired'],
            ['id' => 'sub-3', 'tenant_id' => 'other', 'plan_id' => 'essential', 'billing_cycle_id' => 'annual', 'amount_minor' => 120000, 'currency' => 'MYR', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'active'],
        ]);
        $this->connection()->table('payments')->insert([
            ['id' => 'pay-1', 'tenant_id' => 'tenant-1', 'amount_minor' => 120000, 'currency' => 'MYR', 'status' => 'succeeded', 'domain_last_changed_at' => '2026-07-01 00:00:00+00'],
            ['id' => 'pay-2', 'tenant_id' => 'tenant-2', 'amount_minor' => 5000, 'currency' => 'MYR', 'status' => 'failed', 'domain_last_changed_at' => '2026-07-02 00:00:00+00'],
        ]);
        $this->connection()->table('payment_reconciliation_cases')->insert(['id' => 'case-1', 'status' => 'open']);

        $adapter = new PostgresBillingOverviewReadAdapter($this->connection());
        $summary = $adapter->summary('2026-07-01');
        self::assertSame(2, $summary->activeSubscriptions);
        self::assertSame(1, $summary->expiringSubscriptions);
        self::assertSame(1, $summary->expiredSubscriptions);
        self::assertSame(120000, $summary->annualRevenueMinor);
        self::assertSame(1, $summary->succeededPayments);
        self::assertSame(1, $summary->failedPayments);
        self::assertSame(1, $summary->openReconciliationCases);
        self::assertSame('pay-2', $summary->recentPayments[0]->paymentId);

        $filtered = $adapter->subscriptions('active', null, 10, 'tenant');
        self::assertSame(['sub-1'], array_column($filtered, 'subscriptionId'));
        $paged = $adapter->subscriptions(null, 'sub-1', 10, null);
        self::assertSame(['sub-2', 'sub-3'], array_column($paged, 'subscriptionId'));
    }

    private function connection(): ConnectionInterface
    {
        self::assertNotNull($this->connection);

        return $this->connection;
    }
}
