<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\SubscriptionBilling\Payment;

use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentIntegrationOutboxEvent;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\PublishPaymentOutboxService;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresPaymentOutboxRepository;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class PostgresPaymentOutboxDeliveryTest extends TestCase
{
    private const string CONNECTION = 'payment_outbox_postgres_integration';

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
        $this->insertPayment();
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->migrations) as $migration) {
            $migration->down();
        }
        DB::purge(self::CONNECTION);
        parent::tearDown();
    }

    public function test_first_claim_dispatches_and_marks_delivered_exactly_once(): void
    {
        $id = $this->insertOutboxRow('evt-1');
        $publisher = $this->publisher();

        self::assertTrue($publisher->publishNext());
        self::assertFalse($publisher->publishNext());

        $row = $this->row($id);
        self::assertSame($id, (string) $row->id);
        self::assertSame(1, $row->event_version);
        self::assertNotNull($row->published_at);
        self::assertNull($row->publish_claim_token);
        self::assertNull($row->publish_lease_expires_at);
        self::assertNull($row->next_publish_attempt_at);
        self::assertNull($row->safe_failure_label);
        self::assertSame(1, $this->outboxRowCount());
    }

    public function test_active_lease_protects_a_record_from_being_reclaimed(): void
    {
        $id = $this->insertOutboxRow('evt-1');
        $future = (new DateTimeImmutable('+2 minutes'))->format('Y-m-d H:i:s.uP');
        $this->outbox()->where('id', $id)->update(['publish_claim_token' => '00000000-0000-4000-8000-000000000001', 'publish_lease_expires_at' => $future]);

        self::assertFalse($this->publisher()->publishNext());
        $row = $this->row($id);
        self::assertSame('00000000-0000-4000-8000-000000000001', $row->publish_claim_token);
        self::assertNull($row->published_at);
    }

    public function test_expired_lease_can_be_reclaimed_with_a_new_claim_token(): void
    {
        $id = $this->insertOutboxRow('evt-1');
        $past = (new DateTimeImmutable('-1 minute'))->format('Y-m-d H:i:s.uP');
        $this->outbox()->where('id', $id)->update(['publish_claim_token' => '00000000-0000-4000-8000-000000000002', 'publish_lease_expires_at' => $past]);

        self::assertTrue($this->publisher()->publishNext());

        $row = $this->row($id);
        self::assertNotNull($row->published_at);
        self::assertNull($row->publish_claim_token);
    }

    public function test_a_stale_claim_token_cannot_mark_the_record_delivered(): void
    {
        $id = $this->insertOutboxRow('evt-1');
        $past = (new DateTimeImmutable('-1 minute'))->format('Y-m-d H:i:s.uP');
        $this->outbox()->where('id', $id)->update(['publish_claim_token' => '00000000-0000-4000-8000-000000000003', 'publish_lease_expires_at' => $past]);

        // A new worker reclaims and delivers, replacing the claim token.
        self::assertTrue($this->publisher()->publishNext());
        $publishedAt = $this->row($id)->published_at;
        self::assertNotNull($publishedAt);

        // The crashed worker finally wakes up and tries to complete with its
        // own (now stale) token; it must have zero effect on the row.
        $affected = $this->outbox()->where('id', $id)->where('publish_claim_token', '00000000-0000-4000-8000-000000000003')->whereNull('published_at')->update([
            'published_at' => (new DateTimeImmutable)->format('Y-m-d H:i:s.uP'), 'updated_at' => (new DateTimeImmutable)->format('Y-m-d H:i:s.uP'),
        ]);

        self::assertSame(0, $affected);
        self::assertSame($publishedAt, $this->row($id)->published_at);
    }

    public function test_concurrent_publisher_claim_exactly_one_worker_wins(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required for process-level concurrency verification.');
        }

        $this->insertOutboxRow('evt-1');
        $workspace = sys_get_temp_dir().'/syifa-outbox-claim-'.bin2hex(random_bytes(4));
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
            self::assertSame(1, count(array_filter($results, static fn (array $r): bool => $r['claimed'] === true)));
            self::assertSame(1, count(array_filter($results, static fn (array $r): bool => $r['claimed'] === false)));
            self::assertSame(1, $this->outboxRowCount());
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

        $claimed = $this->publisher()->publishNext();
        file_put_contents($workspace.'/result-'.$childNumber.'.json', json_encode(['claimed' => $claimed], JSON_THROW_ON_ERROR));
    }

    public function test_failed_delivery_does_not_mark_delivered_and_schedules_a_safe_retry(): void
    {
        $id = $this->insertOutboxRow('evt-1');
        $publisher = new PublishPaymentOutboxService($this->db(), $this->throwingDispatcher('sensitive internal stack detail'));

        self::assertTrue($publisher->publishNext());

        $row = $this->row($id);
        self::assertNull($row->published_at);
        self::assertNull($row->publish_claim_token);
        self::assertNull($row->publish_lease_expires_at);
        self::assertNotNull($row->next_publish_attempt_at);
        self::assertSame('outbox_publish_failed', $row->safe_failure_label);
        self::assertSame(1, (int) $row->publish_attempt_count);
        self::assertStringNotContainsString('sensitive internal stack detail', (string) $row->safe_failure_label);
    }

    public function test_stable_event_id_survives_a_failed_attempt_and_a_later_successful_delivery(): void
    {
        $id = $this->insertOutboxRow('evt-1');
        $failingPublisher = new PublishPaymentOutboxService($this->db(), $this->throwingDispatcher('boom'));
        self::assertTrue($failingPublisher->publishNext());
        self::assertSame($id, (string) $this->row($id)->id);

        // Force immediate eligibility for the retry rather than waiting 30s.
        $this->outbox()->where('id', $id)->update(['next_publish_attempt_at' => (new DateTimeImmutable('-1 second'))->format('Y-m-d H:i:s.uP')]);

        self::assertTrue($this->publisher()->publishNext());
        $row = $this->row($id);
        self::assertSame($id, (string) $row->id);
        self::assertNotNull($row->published_at);
    }

    public function test_worker_crash_recovery_another_worker_reclaims_and_delivers(): void
    {
        $id = $this->insertOutboxRow('evt-1');
        // Simulate a worker claiming the row and crashing before completion:
        // set a claim with a lease that has already expired.
        $past = (new DateTimeImmutable('-5 seconds'))->format('Y-m-d H:i:s.uP');
        $this->outbox()->where('id', $id)->update(['publish_claim_token' => '00000000-0000-4000-8000-000000000004', 'publish_lease_expires_at' => $past]);

        $dispatched = [];
        $publisher = new PublishPaymentOutboxService($this->db(), $this->recordingDispatcher($dispatched));

        self::assertTrue($publisher->publishNext());
        self::assertCount(1, $dispatched);
        self::assertSame($id, $dispatched[0]->id);
        self::assertNotNull($this->row($id)->published_at);
    }

    public function test_dispatched_event_carries_event_version_one(): void
    {
        $id = $this->insertOutboxRow('evt-1');
        $dispatched = [];
        $publisher = new PublishPaymentOutboxService($this->db(), $this->recordingDispatcher($dispatched));

        self::assertTrue($publisher->publishNext());
        self::assertCount(1, $dispatched);
        self::assertSame($id, $dispatched[0]->id);
        self::assertSame(1, $dispatched[0]->eventVersion);
    }

    public function test_duplicate_add_calls_with_the_same_event_id_create_only_one_row(): void
    {
        $repository = new PostgresPaymentOutboxRepository($this->db());
        $now = new DateTimeImmutable('2026-07-25T00:00:00Z');

        $repository->add('11111111-1111-4111-8111-111111111100', 'VerifiedPaymentSucceeded', $this->paymentId(), ['payment_id' => $this->paymentId()], $now);
        $repository->add('11111111-1111-4111-8111-111111111100', 'VerifiedPaymentSucceeded', $this->paymentId(), ['payment_id' => $this->paymentId()], $now);

        self::assertSame(1, $this->outboxRowCount());
    }

    public function test_sweep_entry_point_discovers_and_delivers_a_committed_event_without_a_prior_dispatch(): void
    {
        // Simulate the queue-enqueue step being lost entirely: the outbox
        // row is committed but PublishPaymentOutboxJob::dispatch() never ran.
        $id = $this->insertOutboxRow('evt-1');
        $dispatched = [];
        $publisher = new PublishPaymentOutboxService($this->db(), $this->recordingDispatcher($dispatched));

        // This is the sweep/recovery entry point body (PublishPaymentOutboxJob::handle()).
        while ($publisher->publishNext()) {
        }

        self::assertCount(1, $dispatched);
        self::assertSame($id, $dispatched[0]->id);
        self::assertNotNull($this->row($id)->published_at);
    }

    public function test_sweep_is_registered_on_the_laravel_scheduler_without_a_new_scheduler_dependency(): void
    {
        $bootstrap = (string) file_get_contents(base_path('bootstrap/app.php'));

        self::assertStringContainsString('withSchedule', $bootstrap);
        self::assertStringContainsString('PublishPaymentOutboxJob', $bootstrap);
        self::assertStringNotContainsString('cron:', $bootstrap);
    }

    private function publisher(): PublishPaymentOutboxService
    {
        return new PublishPaymentOutboxService($this->db(), $this->app->make(Dispatcher::class));
    }

    private function throwingDispatcher(string $message): Dispatcher
    {
        return new class($message) implements Dispatcher
        {
            public function __construct(private string $message) {}

            public function dispatch($event, $payload = [], $halt = false): mixed
            {
                throw new RuntimeException($this->message);
            }

            public function listen($events, $listener = null): void {}

            public function hasListeners($eventName): bool
            {
                return false;
            }

            public function subscribe($subscriber): void {}

            public function until($event, $payload = []): mixed
            {
                return null;
            }

            public function push($event, $payload = []): void {}

            public function flush($event): void {}

            public function forget($event): void {}

            public function forgetPushed(): void {}
        };
    }

    /** @param list<PaymentIntegrationOutboxEvent> $dispatched */
    private function recordingDispatcher(array &$dispatched): Dispatcher
    {
        return new class($dispatched) implements Dispatcher
        {
            /** @param list<PaymentIntegrationOutboxEvent> $dispatched */
            public function __construct(private array &$dispatched) {}

            public function dispatch($event, $payload = [], $halt = false): mixed
            {
                if ($event instanceof PaymentIntegrationOutboxEvent) {
                    $this->dispatched[] = $event;
                }

                return null;
            }

            public function listen($events, $listener = null): void {}

            public function hasListeners($eventName): bool
            {
                return false;
            }

            public function subscribe($subscriber): void {}

            public function until($event, $payload = []): mixed
            {
                return null;
            }

            public function push($event, $payload = []): void {}

            public function flush($event): void {}

            public function forget($event): void {}

            public function forgetPushed(): void {}
        };
    }

    private function insertOutboxRow(string $eventType): string
    {
        $id = (string) Str::uuid();
        $now = (new DateTimeImmutable)->format('Y-m-d H:i:s.uP');
        $this->outbox()->insert([
            'id' => $id, 'event_type' => $eventType, 'payment_id' => $this->paymentId(),
            'payload' => json_encode(['payment_id' => $this->paymentId()], JSON_THROW_ON_ERROR),
            'occurred_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return $id;
    }

    private function insertPayment(): void
    {
        $now = '2026-07-25 00:00:00+00';
        $this->db()->table('payments')->insert([
            'id' => $this->paymentId(), 'commercial_offer_id' => '22222222-2222-4222-8222-222222222222',
            'clinic_registration_id' => '33333333-3333-4333-8333-333333333333', 'platform_identity_id' => '44444444-4444-4444-8444-444444444444',
            'amount_minor' => 2550, 'currency' => 'MYR', 'idempotency_key' => 'idem', 'status' => 'succeeded',
            'provider_key' => 'provider-a', 'provider_payment_reference' => 'provider-ref', 'failure_reason_code' => null,
            'domain_created_at' => $now, 'domain_last_changed_at' => $now, 'version' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function paymentId(): string
    {
        return '11111111-1111-4111-8111-111111111111';
    }

    private function row(string $id): object
    {
        $row = $this->outbox()->where('id', $id)->first();
        self::assertIsObject($row);

        return $row;
    }

    private function outboxRowCount(): int
    {
        return $this->outbox()->count();
    }

    private function outbox(): Builder
    {
        return $this->db()->table('payment_integration_outbox');
    }

    private function db(): ConnectionInterface
    {
        self::assertInstanceOf(ConnectionInterface::class, $this->connection);

        return $this->connection;
    }
}
