<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\SubscriptionBilling\Payment;

use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresPaymentReconciliationCaseRepository;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PostgresPaymentReconciliationUniquenessTest extends TestCase
{
    private const string CONNECTION = 'payment_reconciliation_postgres_integration';

    private ?ConnectionInterface $connection = null;

    /** @var list<Migration> */
    private array $migrations = [];

    protected function setUp(): void
    {
        parent::setUp();
        $dsn = getenv('SUBSCRIPTION_BILLING_POSTGRES_TEST_DSN');
        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Requires SUBSCRIPTION_BILLING_POSTGRES_TEST_DSN.');
        }
        config()->set('database.default', self::CONNECTION);
        config()->set('database.connections.'.self::CONNECTION, [
            'driver' => 'pgsql', 'url' => $dsn, 'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public', 'sslmode' => 'prefer',
        ]);
        DB::purge(self::CONNECTION);
        $this->connection = DB::connection(self::CONNECTION);
        foreach (['payment_integration_outbox', 'payment_reconciliation_cases', 'payment_verification_applications', 'payment_provider_webhook_receipts', 'payment_attempts', 'payments'] as $table) {
            Schema::connection(self::CONNECTION)->dropIfExists($table);
        }
        foreach ([
            'database/migrations/subscription_billing/2026_07_21_000002_create_payment_core_tables.php',
            'database/migrations/subscription_billing/2026_07_23_000001_create_payment_provider_webhook_receipts.php',
            'database/migrations/subscription_billing/2026_07_24_000001_add_authoritative_verification_to_webhook_receipts.php',
            'database/migrations/subscription_billing/2026_07_24_000002_index_payment_attempt_provider_reference.php',
            'database/migrations/subscription_billing/2026_07_25_000001_create_payment_verification_application_tables.php',
            'database/migrations/subscription_billing/2026_07_26_000001_add_tenant_id_to_payments.php',
            'database/migrations/subscription_billing/2026_07_26_000002_add_event_version_to_payment_integration_outbox.php',
        ] as $path) {
            $migration = require base_path($path);
            $migration->up();
            $this->migrations[] = $migration;
        }
        $this->insertPaymentAndReceipt();
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->migrations) as $migration) {
            $migration->down();
        }
        DB::purge(self::CONNECTION);
        parent::tearDown();
    }

    public function test_postgresql_unique_constraint_exists_on_provider_webhook_receipt_id(): void
    {
        $indexes = $this->connection()->select(
            "select indexdef from pg_indexes where tablename = 'payment_reconciliation_cases' and indexdef like '%UNIQUE%'",
        );

        $matches = array_filter(
            $indexes,
            static fn (object $row): bool => str_contains((string) $row->indexdef, 'provider_webhook_receipt_id'),
        );

        self::assertNotEmpty($matches, 'Expected a unique index covering provider_webhook_receipt_id.');
    }

    public function test_sequential_duplicate_open_returns_the_same_case_and_creates_only_one_row(): void
    {
        $repository = new PostgresPaymentReconciliationCaseRepository($this->connection());
        $now = new DateTimeImmutable('2026-07-25T00:00:00Z');

        $first = $repository->open($this->receiptId(), $this->paymentId(), 'attempt-old', 'historical_success', $now);
        $second = $repository->open($this->receiptId(), $this->paymentId(), 'attempt-old', 'historical_success', $now);

        self::assertSame($first, $second);
        self::assertSame(1, $this->connection()->table('payment_reconciliation_cases')->count());
    }

    public function test_concurrent_open_for_the_same_receipt_creates_only_one_case(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required for process-level concurrency verification.');
        }

        $workspace = sys_get_temp_dir().'/syifa-reconciliation-open-'.bin2hex(random_bytes(4));
        mkdir($workspace);
        $releaseFile = $workspace.'/release';

        try {
            $children = [];
            foreach ([1, 2] as $childNumber) {
                $pid = pcntl_fork();
                if ($pid === -1) {
                    self::fail('Unable to fork concurrency child process.');
                }
                if ($pid === 0) {
                    $this->runConcurrentChild($childNumber, $releaseFile, $workspace);
                    exit(0);
                }
                $children[] = $pid;
            }

            touch($releaseFile);
            foreach ($children as $pid) {
                pcntl_waitpid($pid, $status);
                self::assertSame(0, pcntl_wexitstatus($status));
            }

            $results = array_map(
                static fn (string $file): array => json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR),
                glob($workspace.'/result-*.json') ?: [],
            );

            self::assertCount(2, $results);
            self::assertSame($results[0]['case_id'], $results[1]['case_id']);
            self::assertSame(1, $this->connection()->table('payment_reconciliation_cases')->count());
        } finally {
            foreach (glob($workspace.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($workspace);
        }
    }

    private function runConcurrentChild(int $childNumber, string $releaseFile, string $workspace): void
    {
        DB::purge(self::CONNECTION);
        $this->connection = DB::connection(self::CONNECTION);

        while (! file_exists($releaseFile)) {
            usleep(1000);
        }

        $repository = new PostgresPaymentReconciliationCaseRepository($this->connection());
        $caseId = $repository->open($this->receiptId(), $this->paymentId(), 'attempt-old', 'historical_success', new DateTimeImmutable('2026-07-25T00:00:00Z'));
        file_put_contents($workspace.'/result-'.$childNumber.'.json', json_encode(['case_id' => $caseId], JSON_THROW_ON_ERROR));
    }

    private function insertPaymentAndReceipt(): void
    {
        $now = '2026-07-25 00:00:00+00';
        $this->connection()->table('payments')->insert([
            'id' => $this->paymentId(), 'commercial_offer_id' => '22222222-2222-4222-8222-222222222222', 'clinic_registration_id' => '33333333-3333-4333-8333-333333333333',
            'platform_identity_id' => '44444444-4444-4444-8444-444444444444', 'amount_minor' => 2550, 'currency' => 'MYR', 'idempotency_key' => 'idem',
            'status' => 'pending', 'provider_key' => 'provider-a', 'provider_payment_reference' => 'provider-ref', 'failure_reason_code' => null,
            'domain_created_at' => $now, 'domain_last_changed_at' => $now, 'version' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->connection()->table('payment_provider_webhook_receipts')->insert([
            'id' => $this->receiptId(), 'provider_key' => 'provider-a', 'provider_event_id' => (string) Str::uuid(), 'event_type' => 'payment',
            'status' => 'processed', 'provider_payment_reference' => 'provider-ref', 'signature_verified' => true, 'received_at' => $now,
            'processed_at' => $now, 'verification_attempt_count' => 1, 'resolved_payment_id' => $this->paymentId(), 'resolved_payment_attempt_reference' => 'attempt-old',
            'resolved_attempt_relation' => 'historical', 'verification_outcome' => 'succeeded', 'verified_amount_minor' => 2550, 'verified_currency' => 'MYR',
            'provider_object_correlation_passed' => true, 'environment_correlation_supported' => false, 'environment_correlation_passed' => false,
            'authoritative_verified_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function paymentId(): string
    {
        return '11111111-1111-4111-8111-111111111111';
    }

    private function receiptId(): string
    {
        return '55555555-5555-4555-8555-555555555555';
    }

    private function connection(): ConnectionInterface
    {
        self::assertInstanceOf(ConnectionInterface::class, $this->connection);

        return $this->connection;
    }
}
