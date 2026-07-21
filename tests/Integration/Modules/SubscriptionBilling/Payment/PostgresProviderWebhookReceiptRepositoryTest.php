<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\SubscriptionBilling\Payment;

use App\Modules\SubscriptionBilling\Contracts\Payment\NewProviderWebhookReceiptData;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookReceiptStatus;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\PostgresProviderWebhookReceiptRepository;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PostgresProviderWebhookReceiptRepositoryTest extends TestCase
{
    private const string CONNECTION_NAME = 'provider_webhook_receipt_postgres_integration';

    private ?ConnectionInterface $connection = null;

    /** @var list<Migration> */
    private array $migrations = [];

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
        $this->dropTables();
        $this->migrate();
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->migrations) as $migration) {
            $migration->down();
        }

        DB::purge(self::CONNECTION_NAME);
        parent::tearDown();
    }

    public function test_first_event_registration_creates_one_receipt(): void
    {
        $result = $this->repository()->register($this->event('stripe', 'evt_1'));

        self::assertFalse($result->wasDuplicate);
        self::assertSame('stripe', $result->receipt->providerKey);
        self::assertSame('evt_1', $result->receipt->providerEventId);
        self::assertSame(ProviderWebhookReceiptStatus::Received, $result->receipt->status);
        self::assertSame(1, $this->connection()->table('payment_provider_webhook_receipts')->count());
    }

    public function test_receipt_id_is_a_valid_random_uuid(): void
    {
        $result = $this->repository()->register($this->event('stripe', 'evt_1'));

        self::assertTrue(Str::isUuid($result->receipt->id), 'Expected the receipt id to be a valid UUID.');
    }

    public function test_same_provider_and_event_id_is_detected_as_duplicate(): void
    {
        $repository = $this->repository();
        $first = $repository->register($this->event('stripe', 'evt_1'));
        $second = $repository->register($this->event('stripe', 'evt_1'));

        self::assertFalse($first->wasDuplicate);
        self::assertTrue($second->wasDuplicate);
        self::assertSame($first->receipt->id, $second->receipt->id);
        self::assertSame(1, $this->connection()->table('payment_provider_webhook_receipts')->count());
    }

    public function test_duplicate_registration_returns_the_existing_persisted_id_not_a_freshly_generated_one(): void
    {
        $repository = $this->repository();
        $first = $repository->register($this->event('stripe', 'evt_1'));

        // A duplicate attempt still generates a fresh candidate id internally
        // (discarded on conflict); the result must reflect the row that was
        // actually persisted on the first call, not that discarded value.
        $second = $repository->register($this->event('stripe', 'evt_1'));
        $storedId = $this->connection()->table('payment_provider_webhook_receipts')
            ->where('provider_key', 'stripe')->where('provider_event_id', 'evt_1')->value('id');

        self::assertSame($first->receipt->id, $storedId);
        self::assertSame($first->receipt->id, $second->receipt->id);
    }

    public function test_same_provider_event_id_from_different_providers_does_not_collide(): void
    {
        $repository = $this->repository();
        $stripe = $repository->register($this->event('stripe', 'shared-id'));
        $toyyibpay = $repository->register($this->event('toyyibpay', 'shared-id'));

        self::assertFalse($stripe->wasDuplicate);
        self::assertFalse($toyyibpay->wasDuplicate);
        self::assertNotSame($stripe->receipt->id, $toyyibpay->receipt->id);
        self::assertSame(2, $this->connection()->table('payment_provider_webhook_receipts')->count());
    }

    public function test_different_provider_event_ids_register_independently(): void
    {
        $repository = $this->repository();
        $repository->register($this->event('stripe', 'evt_1'));
        $repository->register($this->event('stripe', 'evt_2'));

        self::assertSame(2, $this->connection()->table('payment_provider_webhook_receipts')->count());
    }

    public function test_provider_event_type_is_persisted(): void
    {
        $result = $this->repository()->register($this->event('stripe', 'evt_1', eventType: 'checkout.session.completed'));

        self::assertSame('checkout.session.completed', $result->receipt->eventType);
        self::assertSame(
            'checkout.session.completed',
            $this->connection()->table('payment_provider_webhook_receipts')->value('event_type'),
        );
    }

    public function test_received_timestamp_is_persisted_correctly(): void
    {
        $receivedAt = new DateTimeImmutable('2026-07-23T12:34:56.789012Z');
        $result = $this->repository()->register($this->event('stripe', 'evt_1', receivedAt: $receivedAt));

        $storedInUtc = $result->receipt->receivedAt->setTimezone(new DateTimeZone('UTC'));
        self::assertSame($receivedAt->format('Y-m-d H:i:s'), $storedInUtc->format('Y-m-d H:i:s'));
    }

    public function test_optional_payment_attempt_association_is_persisted_when_supplied(): void
    {
        $result = $this->repository()->register($this->event('stripe', 'evt_1', paymentAttemptReference: 'attempt-1'));

        self::assertSame('attempt-1', $result->receipt->paymentAttemptReference);
        self::assertSame(
            'attempt-1',
            $this->connection()->table('payment_provider_webhook_receipts')->value('payment_attempt_reference'),
        );
    }

    public function test_optional_payment_association_is_persisted_when_supplied(): void
    {
        $paymentId = $this->insertPayment();
        $result = $this->repository()->register($this->event('stripe', 'evt_1', paymentId: $paymentId));

        self::assertSame($paymentId, $result->receipt->paymentId);
        self::assertSame($paymentId, $this->connection()->table('payment_provider_webhook_receipts')->value('payment_id'));
    }

    public function test_no_raw_secret_or_payload_is_persisted(): void
    {
        $result = $this->repository()->register($this->event('stripe', 'evt_1', payloadHash: hash('sha256', 'raw-payload-bytes')));

        $columns = Schema::connection(self::CONNECTION_NAME)->getColumnListing('payment_provider_webhook_receipts');
        foreach (['raw_payload', 'payload', 'secret', 'signature_secret', 'webhook_secret', 'authorization_header'] as $forbiddenColumn) {
            self::assertNotContains($forbiddenColumn, $columns);
        }

        self::assertSame(64, strlen((string) $result->receipt->payloadHash));
        self::assertStringNotContainsString('raw-payload-bytes', (string) $result->receipt->payloadHash);
    }

    public function test_disabled_provider_status_does_not_prevent_receipt_registration(): void
    {
        // The provider configuration migration seeds toyyibpay as disabled by
        // default (enabled=false); the receipt repository never consults
        // PaymentProviderConfigurationRepositoryInterface at all, so a
        // disabled provider must still be able to register a receipt for an
        // existing event (ADR-009: disabling blocks new attempts, not
        // verification/webhook handling for existing ones).
        self::assertFalse((bool) $this->connection()->table('payment_provider_configurations')
            ->where('provider_key', 'toyyibpay')->value('enabled'));

        $result = $this->repository()->register($this->event('toyyibpay', 'evt_1'));

        self::assertFalse($result->wasDuplicate);
        self::assertSame(1, $this->connection()->table('payment_provider_webhook_receipts')->count());
    }

    public function test_postgresql_unique_constraint_exists_on_provider_and_event_id(): void
    {
        $indexes = $this->connection()->select(
            "select indexdef from pg_indexes where tablename = 'payment_provider_webhook_receipts' and indexdef like '%UNIQUE%'",
        );

        $matches = array_filter(
            $indexes,
            static fn (object $row): bool => str_contains((string) $row->indexdef, 'provider_key') && str_contains((string) $row->indexdef, 'provider_event_id'),
        );

        self::assertNotEmpty($matches, 'Expected a unique index covering (provider_key, provider_event_id).');
    }

    public function test_concurrent_duplicate_registration_produces_only_one_database_row(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required for process-level concurrency verification.');
        }

        $workspace = sys_get_temp_dir().'/syifa-webhook-receipt-concurrency-'.bin2hex(random_bytes(4));
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
            self::assertSame(1, count(array_filter($results, static fn (array $result): bool => $result['was_duplicate'] === false)));
            self::assertSame(1, count(array_filter($results, static fn (array $result): bool => $result['was_duplicate'] === true)));
            self::assertSame(
                $results[0]['receipt_id'],
                $results[1]['receipt_id'],
                'Both processes must observe the same single receipt row.',
            );
            self::assertSame(1, $this->connection()->table('payment_provider_webhook_receipts')->count());
        } finally {
            foreach (glob($workspace.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($workspace);
        }
    }

    private function runConcurrentChild(int $childNumber, string $releaseFile, string $workspace): void
    {
        DB::purge(self::CONNECTION_NAME);
        $this->connection = DB::connection(self::CONNECTION_NAME);

        while (! file_exists($releaseFile)) {
            usleep(1000);
        }

        $result = (new PostgresProviderWebhookReceiptRepository($this->connection()))
            ->register($this->event('stripe', 'shared-concurrent-event'));

        file_put_contents($workspace.'/result-'.$childNumber.'.json', json_encode([
            'was_duplicate' => $result->wasDuplicate,
            'receipt_id' => $result->receipt->id,
        ], JSON_THROW_ON_ERROR));
    }

    private function repository(): PostgresProviderWebhookReceiptRepository
    {
        return new PostgresProviderWebhookReceiptRepository($this->connection());
    }

    private function event(
        string $providerKey,
        string $providerEventId,
        string $eventType = 'payment.succeeded',
        ?DateTimeImmutable $receivedAt = null,
        ?string $paymentAttemptReference = null,
        ?string $paymentId = null,
        ?string $payloadHash = null,
    ): NewProviderWebhookReceiptData {
        return new NewProviderWebhookReceiptData(
            providerKey: $providerKey,
            providerEventId: $providerEventId,
            eventType: $eventType,
            receivedAt: $receivedAt ?? new DateTimeImmutable('2026-07-23T00:00:00Z'),
            providerPaymentReference: 'provider-ref-1',
            paymentAttemptReference: $paymentAttemptReference,
            paymentId: $paymentId,
            signatureVerified: true,
            payloadHash: $payloadHash,
        );
    }

    private function insertPayment(): string
    {
        $id = '11111111-1111-4111-8111-111111111111';
        $now = now();
        $this->connection()->table('payments')->insert([
            'id' => $id,
            'commercial_offer_id' => '22222222-2222-4222-8222-222222222222',
            'clinic_registration_id' => '33333333-3333-4333-8333-333333333333',
            'platform_identity_id' => '44444444-4444-4444-8444-444444444444',
            'amount_minor' => 2550,
            'currency' => 'MYR',
            'idempotency_key' => 'idem-1',
            'status' => 'pending',
            'provider_key' => 'stripe',
            'provider_payment_reference' => 'provider-ref-1',
            'failure_reason_code' => null,
            'domain_created_at' => $now,
            'domain_last_changed_at' => $now,
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $id;
    }

    private function connection(): ConnectionInterface
    {
        self::assertInstanceOf(ConnectionInterface::class, $this->connection);

        return $this->connection;
    }

    private function dropTables(): void
    {
        Schema::connection(self::CONNECTION_NAME)->dropIfExists('payment_provider_webhook_receipts');
        Schema::connection(self::CONNECTION_NAME)->dropIfExists('payment_provider_configurations');
        Schema::connection(self::CONNECTION_NAME)->dropIfExists('payment_attempts');
        Schema::connection(self::CONNECTION_NAME)->dropIfExists('payments');
    }

    private function migrate(): void
    {
        foreach ([
            'database/migrations/subscription_billing/2026_07_21_000002_create_payment_core_tables.php',
            'database/migrations/subscription_billing/2026_07_22_000001_create_payment_provider_configurations.php',
            'database/migrations/subscription_billing/2026_07_23_000001_create_payment_provider_webhook_receipts.php',
        ] as $path) {
            $migration = require base_path($path);
            self::assertInstanceOf(Migration::class, $migration);
            $migration->up();
            $this->migrations[] = $migration;
        }
    }
}
