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
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentIntegrationOutboxEvent;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\PostgresPaymentApplicationTransaction;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\PostgresProviderWebhookReceiptRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\PublishPaymentOutboxService;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\PaymentPersistenceMapper;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresPaymentOutboxRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresPaymentReconciliationCaseRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresPaymentRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresPaymentVerificationApplicationRepository;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PostgresPaymentVerificationApplicationTest extends TestCase
{
    private const string CONNECTION = 'payment_application_postgres_integration';

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
        config()->set('database.connections.'.self::CONNECTION, ['driver' => 'pgsql', 'url' => $dsn, 'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public', 'sslmode' => 'prefer']);
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

    public function test_success_commits_payment_system_audit_application_and_outbox_atomically(): void
    {
        [$applicationId, $paymentId] = $this->seedPaymentApplication('pending', 'succeeded');
        $this->service()->execute($applicationId);
        self::assertSame('succeeded', $this->db()->table('payments')->where('id', $paymentId)->value('status'));
        self::assertSame('applied', $this->db()->table('payment_verification_applications')->where('id', $applicationId)->value('status'));
        self::assertSame('system', $this->db()->table('audit_entries')->value('actor_type'));
        self::assertSame('VerifiedPaymentSucceeded', $this->db()->table('payment_integration_outbox')->value('event_type'));
        self::assertNull($this->db()->table('payment_integration_outbox')->value('published_at'));
    }

    public function test_pending_and_action_required_transitions_apply_to_current_attempt(): void
    {
        [$applicationId, $paymentId] = $this->seedPaymentApplication('pending', 'action_required');
        $this->service()->execute($applicationId);
        self::assertSame('action_required', $this->db()->table('payments')->where('id', $paymentId)->value('status'));
        self::assertSame('action_required', $this->db()->table('payment_attempts')->where('payment_id', $paymentId)->value('status'));
    }

    public function test_historical_success_creates_one_case_without_mutating_payment(): void
    {
        [$applicationId, $paymentId, $receiptId] = $this->seedPaymentApplication('pending', 'succeeded', historical: true);
        $service = $this->service();
        $service->execute($applicationId);
        $service->execute($applicationId);
        self::assertSame('pending', $this->db()->table('payments')->where('id', $paymentId)->value('status'));
        self::assertSame('reconciliation_required', $this->db()->table('payment_verification_applications')->where('id', $applicationId)->value('status'));
        self::assertSame(1, $this->db()->table('payment_reconciliation_cases')->where('provider_webhook_receipt_id', $receiptId)->count());
        self::assertSame(1, $this->db()->table('payment_integration_outbox')->count());
    }

    public function test_current_failure_is_applied(): void
    {
        [$applicationId, $paymentId] = $this->seedPaymentApplication('action_required', 'failed');
        $this->service()->execute($applicationId);
        self::assertSame('failed', $this->db()->table('payments')->where('id', $paymentId)->value('status'));
        self::assertSame('VerifiedPaymentFailed', $this->db()->table('payment_integration_outbox')->value('event_type'));
    }

    public function test_current_expiry_is_applied(): void
    {
        [$applicationId, $paymentId] = $this->seedPaymentApplication('pending', 'expired');
        $this->service()->execute($applicationId);
        self::assertSame('expired', $this->db()->table('payments')->where('id', $paymentId)->value('status'));
        self::assertSame('PaymentExpired', $this->db()->table('payment_integration_outbox')->value('event_type'));
    }

    public function test_action_required_returns_to_pending_without_outbox_event(): void
    {
        [$applicationId, $paymentId] = $this->seedPaymentApplication('action_required', 'pending');
        $this->service()->execute($applicationId);
        self::assertSame('pending', $this->db()->table('payments')->where('id', $paymentId)->value('status'));
        self::assertSame(0, $this->db()->table('payment_integration_outbox')->count());
    }

    public function test_pending_noop_is_ignored_without_mutation_or_outbox(): void
    {
        [$applicationId, $paymentId] = $this->seedPaymentApplication('pending', 'pending');
        $this->service()->execute($applicationId);
        self::assertSame('pending', $this->db()->table('payments')->where('id', $paymentId)->value('status'));
        self::assertSame('ignored', $this->db()->table('payment_verification_applications')->where('id', $applicationId)->value('status'));
        self::assertSame(0, $this->db()->table('payment_integration_outbox')->count());
    }

    public function test_failed_same_attempt_success_opens_reconciliation_without_transition(): void
    {
        [$applicationId, $paymentId] = $this->seedPaymentApplication('failed', 'succeeded');
        $this->service()->execute($applicationId);
        self::assertSame('failed', $this->db()->table('payments')->where('id', $paymentId)->value('status'));
        self::assertSame(1, $this->db()->table('payment_reconciliation_cases')->count());
        self::assertSame('PaymentReconciliationRequired', $this->db()->table('payment_integration_outbox')->value('event_type'));
    }

    public function test_terminal_non_success_is_ignored_as_regressive(): void
    {
        [$applicationId, $paymentId] = $this->seedPaymentApplication('expired', 'failed');
        $this->service()->execute($applicationId);
        self::assertSame('expired', $this->db()->table('payments')->where('id', $paymentId)->value('status'));
        self::assertSame('regressive', $this->db()->table('payment_verification_applications')->where('id', $applicationId)->value('result_code'));
    }

    public function test_audit_failure_rolls_back_payment_and_application(): void
    {
        [$applicationId, $paymentId] = $this->seedPaymentApplication('pending', 'succeeded');
        $this->installRejectTrigger('audit_entries', 'reject_audit', 'BEFORE INSERT');
        $this->service()->execute($applicationId);
        self::assertSame('pending', $this->db()->table('payments')->where('id', $paymentId)->value('status'));
        self::assertSame('retry_pending', $this->db()->table('payment_verification_applications')->where('id', $applicationId)->value('status'));
        self::assertSame(0, $this->db()->table('payment_integration_outbox')->count());
    }

    public function test_outbox_failure_rolls_back_payment_audit_and_application(): void
    {
        [$applicationId, $paymentId] = $this->seedPaymentApplication('pending', 'succeeded');
        $this->installRejectTrigger('payment_integration_outbox', 'reject_outbox', 'BEFORE INSERT');
        $this->service()->execute($applicationId);
        self::assertSame('pending', $this->db()->table('payments')->where('id', $paymentId)->value('status'));
        self::assertSame(0, $this->db()->table('audit_entries')->count());
        self::assertSame('retry_pending', $this->db()->table('payment_verification_applications')->where('id', $applicationId)->value('status'));
    }

    public function test_application_completion_failure_rolls_back_payment_audit_and_outbox(): void
    {
        [$applicationId, $paymentId] = $this->seedPaymentApplication('pending', 'succeeded');
        $this->db()->statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION reject_application_completion() RETURNS trigger AS $$ BEGIN
              IF NEW.status = 'applied' THEN RAISE EXCEPTION 'forced completion failure'; END IF; RETURN NEW;
            END; $$ LANGUAGE plpgsql
            SQL);
        $this->db()->statement('CREATE TRIGGER reject_application_completion_trigger BEFORE UPDATE ON payment_verification_applications FOR EACH ROW EXECUTE FUNCTION reject_application_completion()');
        $this->service()->execute($applicationId);
        self::assertSame('pending', $this->db()->table('payments')->where('id', $paymentId)->value('status'));
        self::assertSame(0, $this->db()->table('audit_entries')->count());
        self::assertSame(0, $this->db()->table('payment_integration_outbox')->count());
    }

    public function test_outbox_publisher_claims_committed_event_once(): void
    {
        [$applicationId] = $this->seedPaymentApplication('pending', 'succeeded');
        $this->service()->execute($applicationId);
        Event::fake([PaymentIntegrationOutboxEvent::class]);
        $publisher = new PublishPaymentOutboxService($this->db(), $this->app->make(Dispatcher::class));
        self::assertTrue($publisher->publishNext());
        self::assertFalse($publisher->publishNext());
        self::assertNotNull($this->db()->table('payment_integration_outbox')->value('published_at'));
        Event::assertDispatched(PaymentIntegrationOutboxEvent::class, 1);
    }

    /** @return array{string,string,string} */
    private function seedPaymentApplication(string $paymentStatus, string $outcome, bool $historical = false): array
    {
        $now = '2026-07-25 00:00:00+00';
        $paymentId = '11111111-1111-4111-8111-111111111111';
        $this->db()->table('payments')->insert(['id' => $paymentId, 'commercial_offer_id' => '22222222-2222-4222-8222-222222222222', 'clinic_registration_id' => '33333333-3333-4333-8333-333333333333', 'platform_identity_id' => '44444444-4444-4444-8444-444444444444', 'amount_minor' => 2550, 'currency' => 'MYR', 'idempotency_key' => 'idem', 'status' => $paymentStatus, 'provider_key' => 'provider-a', 'provider_payment_reference' => $historical ? 'new-ref' : 'provider-ref', 'failure_reason_code' => null, 'domain_created_at' => $now, 'domain_last_changed_at' => $now, 'version' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $attempts = [['aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'attempt-old', 'provider-ref', 0]];
        if ($historical) {
            $attempts[] = ['bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'attempt-new', 'new-ref', 1];
        }
        foreach ($attempts as [$id,$reference,$providerReference,$position]) {
            $this->db()->table('payment_attempts')->insert(['id' => $id, 'payment_id' => $paymentId, 'attempt_reference' => $reference, 'status' => $position === array_key_last($attempts) ? $paymentStatus : 'failed', 'provider_key' => 'provider-a', 'provider_payment_reference' => $providerReference, 'failure_reason_code' => null, 'started_at' => $now, 'last_changed_at' => $now, 'position' => $position, 'created_at' => $now, 'updated_at' => $now]);
        }
        $receiptId = '55555555-5555-4555-8555-555555555555';
        $this->db()->table('payment_provider_webhook_receipts')->insert(['id' => $receiptId, 'provider_key' => 'provider-a', 'provider_event_id' => 'event-1', 'event_type' => 'payment', 'status' => 'processed', 'provider_payment_reference' => 'provider-ref', 'signature_verified' => true, 'received_at' => $now, 'processed_at' => $now, 'verification_attempt_count' => 1, 'resolved_payment_id' => $paymentId, 'resolved_payment_attempt_reference' => 'attempt-old', 'resolved_attempt_relation' => $historical ? 'historical' : 'current', 'verification_outcome' => $outcome, 'verified_amount_minor' => 2550, 'verified_currency' => 'MYR', 'provider_object_correlation_passed' => true, 'environment_correlation_supported' => false, 'environment_correlation_passed' => false, 'authoritative_verified_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        $application = (new PostgresPaymentVerificationApplicationRepository($this->db()))->register($receiptId, new DateTimeImmutable($now));

        return [$application->id, $paymentId, $receiptId];
    }

    private function service(): ApplyAuthoritativePaymentVerificationService
    {
        $db = $this->db();
        $applications = new PostgresPaymentVerificationApplicationRepository($db);
        $audit = new RecordAuditEntryService(new PostgresAuditEntryRepository($db, new AuditEntryPersistenceMapper));

        return new ApplyAuthoritativePaymentVerificationService($applications, new PostgresProviderWebhookReceiptRepository($db), new PostgresPaymentRepository($db, new PaymentPersistenceMapper), new PostgresPaymentReconciliationCaseRepository($db), new PostgresPaymentOutboxRepository($db), new PaymentVerificationFinancialAuditTrail($audit), new PostgresPaymentApplicationTransaction($db), new NoopApplicationJobs, new PaymentApplicationRetryPolicy);
    }

    private function installRejectTrigger(string $table, string $name, string $timing): void
    {
        $this->db()->statement("CREATE OR REPLACE FUNCTION {$name}() RETURNS trigger AS $$ BEGIN RAISE EXCEPTION 'forced local failure'; END; $$ LANGUAGE plpgsql");
        $this->db()->statement("CREATE TRIGGER {$name}_trigger {$timing} ON {$table} FOR EACH ROW EXECUTE FUNCTION {$name}()");
    }

    private function db(): ConnectionInterface
    {
        self::assertInstanceOf(ConnectionInterface::class, $this->connection);

        return $this->connection;
    }
}

final class NoopApplicationJobs implements PaymentApplicationJobDispatcherInterface
{
    public function dispatch(string $applicationId, int $delaySeconds = 0): void {}
}
