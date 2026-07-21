<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\SubscriptionBilling\Payment;

use App\Modules\PlatformAdministration\Application\AuditEntry\RecordAuditEntryService;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\AuditEntry\Mappers\AuditEntryPersistenceMapper;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\AuditEntry\PostgresAuditEntryRepository;
use App\Modules\SubscriptionBilling\Application\Payment\ApplyAuthoritativePaymentVerificationService;
use App\Modules\SubscriptionBilling\Application\Payment\PaymentApplicationRetryPolicy;
use App\Modules\SubscriptionBilling\Application\Payment\PaymentVerificationFinancialAuditTrail;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentApplicationJobDispatcherInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentVerificationApplicationResultCode;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentVerificationApplicationStatus;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\PostgresPaymentApplicationTransaction;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\PostgresProviderWebhookReceiptRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\PaymentPersistenceMapper;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresPaymentOutboxRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresPaymentReconciliationCaseRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresPaymentRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresPaymentVerificationApplicationRepository;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PostgresPaymentVerificationApplicationConcurrencyTest extends TestCase
{
    private const string CONNECTION = 'payment_application_concurrency_postgres_integration';

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
        foreach (['payment_integration_outbox', 'payment_reconciliation_cases', 'payment_verification_applications', 'payment_provider_webhook_receipts', 'payment_attempts', 'payments', 'audit_entries'] as $table) {
            Schema::connection(self::CONNECTION)->dropIfExists($table);
        }
        foreach ([
            'database/migrations/platform_administration/2026_07_20_000001_create_audit_entries_table.php',
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
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->migrations) as $migration) {
            $migration->down();
        }
        DB::purge(self::CONNECTION);
        parent::tearDown();
    }

    public function test_active_lease_prevents_a_second_claim(): void
    {
        $receiptId = $this->insertReceipt();
        $application = $this->repository()->register($receiptId, new DateTimeImmutable);

        $first = $this->repository()->claim($application->id, new DateTimeImmutable, 120);
        self::assertNotNull($first);
        self::assertNotNull($first->claimToken);

        $second = $this->repository()->claim($application->id, new DateTimeImmutable, 120);
        self::assertNull($second);
    }

    public function test_expired_lease_can_be_reclaimed_with_a_new_claim_token(): void
    {
        $receiptId = $this->insertReceipt();
        $application = $this->repository()->register($receiptId, new DateTimeImmutable);

        $first = $this->repository()->claim($application->id, new DateTimeImmutable('-5 minutes'), 1);
        self::assertNotNull($first);

        $second = $this->repository()->claim($application->id, new DateTimeImmutable, 120);
        self::assertNotNull($second);
        self::assertNotSame($first->claimToken, $second->claimToken);
    }

    public function test_a_stale_claim_token_cannot_complete_the_application(): void
    {
        $receiptId = $this->insertReceipt();
        $application = $this->repository()->register($receiptId, new DateTimeImmutable);

        $stale = $this->repository()->claim($application->id, new DateTimeImmutable('-5 minutes'), 1);
        self::assertNotNull($stale);

        // A new worker reclaims the now-expired lease with a fresh token.
        $current = $this->repository()->claim($application->id, new DateTimeImmutable, 120);
        self::assertNotNull($current);

        $completedWithStaleToken = $this->repository()->complete(
            $application->id, $stale->claimToken, PaymentVerificationApplicationStatus::Applied,
            PaymentVerificationApplicationResultCode::Applied, new DateTimeImmutable,
        );

        self::assertFalse($completedWithStaleToken);
        self::assertSame('processing', $this->connection()->table('payment_verification_applications')->where('id', $application->id)->value('status'));
    }

    public function test_worker_crash_recovery_a_new_worker_reclaims_after_lease_expiry(): void
    {
        $receiptId = $this->insertReceipt();
        $application = $this->repository()->register($receiptId, new DateTimeImmutable);

        // Simulate a worker crashing mid-processing: claimed, never completed.
        $crashed = $this->repository()->claim($application->id, new DateTimeImmutable('-5 minutes'), 1);
        self::assertNotNull($crashed);

        $recovered = $this->repository()->claim($application->id, new DateTimeImmutable, 120);
        self::assertNotNull($recovered);
        self::assertTrue($this->repository()->complete(
            $application->id, $recovered->claimToken, PaymentVerificationApplicationStatus::Applied,
            PaymentVerificationApplicationResultCode::Applied, new DateTimeImmutable,
        ));
        self::assertSame('applied', $this->connection()->table('payment_verification_applications')->where('id', $application->id)->value('status'));
    }

    public function test_concurrent_claim_exactly_one_worker_wins(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required for process-level concurrency verification.');
        }

        $receiptId = $this->insertReceipt();
        $application = $this->repository()->register($receiptId, new DateTimeImmutable);
        $workspace = sys_get_temp_dir().'/syifa-application-claim-'.bin2hex(random_bytes(4));
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
                    $this->runConcurrentChild($childNumber, $application->id, $releaseFile, $workspace);
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
        } finally {
            foreach (glob($workspace.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($workspace);
        }
    }

    private function runConcurrentChild(int $childNumber, string $applicationId, string $releaseFile, string $workspace): void
    {
        DB::purge(self::CONNECTION);
        $this->connection = DB::connection(self::CONNECTION);

        while (! file_exists($releaseFile)) {
            usleep(1000);
        }

        $claim = $this->repository()->claim($applicationId, new DateTimeImmutable, 120);
        file_put_contents($workspace.'/result-'.$childNumber.'.json', json_encode(['claimed' => $claim !== null], JSON_THROW_ON_ERROR));
    }

    public function test_duplicate_application_job_execution_does_not_create_a_duplicate_financial_audit(): void
    {
        [$applicationId] = $this->seedFullPaymentApplication();
        $service = $this->service();

        $service->execute($applicationId);
        self::assertSame('applied', $this->connection()->table('payment_verification_applications')->where('id', $applicationId)->value('status'));
        self::assertSame(1, $this->connection()->table('audit_entries')->count());
        self::assertSame(1, $this->connection()->table('payment_integration_outbox')->count());

        // Simulate a duplicate queue delivery of the same application job.
        $service->execute($applicationId);

        self::assertSame(1, $this->connection()->table('audit_entries')->count());
        self::assertSame(1, $this->connection()->table('payment_integration_outbox')->count());
    }

    public function test_duplicate_ignored_processing_does_not_create_a_duplicate_audit_entry(): void
    {
        [$applicationId] = $this->seedFullPaymentApplication(outcome: 'pending');
        $service = $this->service();

        $service->execute($applicationId);
        self::assertSame('ignored', $this->connection()->table('payment_verification_applications')->where('id', $applicationId)->value('status'));
        self::assertSame(1, $this->connection()->table('audit_entries')->count());

        // Simulate a duplicate queue delivery of the same (already-ignored) application job.
        $service->execute($applicationId);

        self::assertSame(1, $this->connection()->table('audit_entries')->count());
    }

    private function repository(): PostgresPaymentVerificationApplicationRepository
    {
        return new PostgresPaymentVerificationApplicationRepository($this->connection());
    }

    private function service(): ApplyAuthoritativePaymentVerificationService
    {
        $db = $this->connection();
        $applications = new PostgresPaymentVerificationApplicationRepository($db);
        $audit = new RecordAuditEntryService(new PostgresAuditEntryRepository($db, new AuditEntryPersistenceMapper));

        return new ApplyAuthoritativePaymentVerificationService(
            $applications, new PostgresProviderWebhookReceiptRepository($db), new PostgresPaymentRepository($db, new PaymentPersistenceMapper),
            new PostgresPaymentReconciliationCaseRepository($db), new PostgresPaymentOutboxRepository($db),
            new PaymentVerificationFinancialAuditTrail($audit), new PostgresPaymentApplicationTransaction($db),
            new class implements PaymentApplicationJobDispatcherInterface
            {
                public function dispatch(string $applicationId, int $delaySeconds = 0): void {}
            },
            new PaymentApplicationRetryPolicy,
        );
    }

    /** @return array{string,string} */
    private function seedFullPaymentApplication(string $outcome = 'succeeded'): array
    {
        $now = '2026-07-25 00:00:00+00';
        $paymentId = '11111111-1111-4111-8111-111111111111';
        $this->connection()->table('payments')->insert([
            'id' => $paymentId, 'commercial_offer_id' => '22222222-2222-4222-8222-222222222222', 'clinic_registration_id' => '33333333-3333-4333-8333-333333333333',
            'platform_identity_id' => '44444444-4444-4444-8444-444444444444', 'amount_minor' => 2550, 'currency' => 'MYR', 'idempotency_key' => 'idem',
            'status' => 'pending', 'provider_key' => 'provider-a', 'provider_payment_reference' => 'provider-ref', 'failure_reason_code' => null,
            'domain_created_at' => $now, 'domain_last_changed_at' => $now, 'version' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->connection()->table('payment_attempts')->insert([
            'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'payment_id' => $paymentId, 'attempt_reference' => 'attempt-old', 'status' => 'pending',
            'provider_key' => 'provider-a', 'provider_payment_reference' => 'provider-ref', 'failure_reason_code' => null,
            'started_at' => $now, 'last_changed_at' => $now, 'position' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $receiptId = $this->insertReceipt($paymentId, $outcome);
        $application = $this->repository()->register($receiptId, new DateTimeImmutable($now));

        return [$application->id, $paymentId];
    }

    private function insertReceipt(?string $paymentId = null, string $outcome = 'succeeded'): string
    {
        $now = '2026-07-25 00:00:00+00';
        $receiptId = (string) Str::uuid();
        $this->connection()->table('payment_provider_webhook_receipts')->insert([
            'id' => $receiptId, 'provider_key' => 'provider-a', 'provider_event_id' => (string) Str::uuid(),
            'event_type' => 'payment', 'status' => 'processed', 'provider_payment_reference' => 'provider-ref', 'signature_verified' => true,
            'received_at' => $now, 'processed_at' => $now, 'verification_attempt_count' => 1, 'resolved_payment_id' => $paymentId,
            'resolved_payment_attempt_reference' => $paymentId === null ? null : 'attempt-old', 'resolved_attempt_relation' => $paymentId === null ? null : 'current',
            'verification_outcome' => $outcome, 'verified_amount_minor' => 2550, 'verified_currency' => 'MYR',
            'provider_object_correlation_passed' => true, 'environment_correlation_supported' => false, 'environment_correlation_passed' => false,
            'authoritative_verified_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return $receiptId;
    }

    private function connection(): ConnectionInterface
    {
        self::assertInstanceOf(ConnectionInterface::class, $this->connection);

        return $this->connection;
    }
}
