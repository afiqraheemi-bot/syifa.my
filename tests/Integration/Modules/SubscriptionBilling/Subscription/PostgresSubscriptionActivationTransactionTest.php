<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\SubscriptionBilling\Subscription;

use App\Modules\SubscriptionBilling\Application\Subscription\ActivateSubscriptionFromVerifiedPaymentService;
use App\Modules\SubscriptionBilling\Application\Subscription\AnnualTermCalculator;
use App\Modules\SubscriptionBilling\Application\Subscription\SubscriptionActivationRetryPolicy;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\ResolvedSubscriptionOfferingData;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\ComputedSubscriptionEntitlementData;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\SubscriptionEntitlementComputationInterface;
use App\Modules\SubscriptionBilling\Contracts\Repositories\SubscriptionRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivatedIntegrationEvent;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationApplication;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationApplicationRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationApplicationResultCode;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationApplicationStatus;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationAuditInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionIntegrationOutboxClaim;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionIntegrationOutboxRepositoryInterface;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\Subscription;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\PaymentId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\SubscriptionId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\TenantId;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\SubscriptionActivationApplicationPersistenceMapper;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\SubscriptionIntegrationOutboxPersistenceMapper;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\SubscriptionPersistenceMapper;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresSubscriptionActivationApplicationRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresSubscriptionActivationReconciliationCaseRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresSubscriptionIntegrationOutboxRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresSubscriptionRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Subscription\PostgresSubscriptionActivationEvidenceRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Subscription\PostgresSubscriptionActivationTransaction;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

final class PostgresSubscriptionActivationTransactionTest extends TestCase
{
    private const string CONNECTION = 'subscription_activation_transaction_postgres';

    private ?ConnectionInterface $connection = null;

    /** @var list<Migration> */
    private array $migrations = [];

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
        $this->dropTables();
        $this->createEvidenceTables();
        foreach (['2026_07_27_000001_create_subscriptions_table.php', '2026_07_28_000001_create_subscription_activation_tables.php', '2026_07_29_000001_create_subscription_integration_outbox.php', '2026_10_05_000001_allow_exhausted_activation_result_code.php'] as $file) {
            $migration = require base_path('database/migrations/subscription_billing/'.$file);
            self::assertInstanceOf(Migration::class, $migration);
            $migration->up();
            $this->migrations[] = $migration;
        }
    }

    protected function tearDown(): void
    {
        if ($this->connection === null) {
            parent::tearDown();

            return;
        }

        // A row left with result_code='exhausted' would otherwise make the
        // widen-result-code migration's down() fail its own re-narrowed
        // CHECK constraint — clear state before reversing migrations, not
        // just before dropping tables.
        $this->connection()->table('subscription_activation_reconciliation_cases')->delete();
        $this->connection()->table('subscription_activation_applications')->delete();
        foreach (array_reverse($this->migrations) as $migration) {
            $migration->down();
        }
        $this->dropTables();
        DB::purge(self::CONNECTION);
        parent::tearDown();
    }

    public function test_complete_activation_persists_subscription_application_and_audit_atomically(): void
    {
        [$service, $applicationId] = $this->service(false);
        $service->execute($applicationId, $this->time());
        self::assertSame('active', $this->connection()->table('subscriptions')->value('status'));
        self::assertSame('applied', $this->connection()->table('subscription_activation_applications')->value('status'));
        self::assertSame('subscription.activation.applied', $this->connection()->table('subscription_activation_test_audits')->value('action'));
        self::assertSame('SubscriptionActivated', $this->connection()->table('subscription_integration_outbox')->value('event_type'));
    }

    public function test_audit_failure_rolls_back_subscription_and_application_completion(): void
    {
        [$service, $applicationId] = $this->service(true);
        try {
            $service->execute($applicationId, $this->time());
            self::fail('Expected audit failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('forced audit failure', $exception->getMessage());
        }
        self::assertSame(0, $this->connection()->table('subscriptions')->count());
        self::assertSame('processing', $this->connection()->table('subscription_activation_applications')->value('status'));
        self::assertSame(0, $this->connection()->table('subscription_activation_test_audits')->count());
        self::assertSame(0, $this->connection()->table('subscription_integration_outbox')->count());
    }

    public function test_subscription_save_failure_rolls_back_the_application_completion_and_leaves_no_outbox_row(): void
    {
        [, $applicationId] = $this->service(false);
        $connection = $this->connection();
        $service = new ActivateSubscriptionFromVerifiedPaymentService(
            new PostgresSubscriptionActivationApplicationRepository($connection, new SubscriptionActivationApplicationPersistenceMapper),
            new PostgresSubscriptionActivationEvidenceRepository($connection),
            new FailingSubscriptionRepository(new PostgresSubscriptionRepository($connection, new SubscriptionPersistenceMapper)),
            new TestEntitlementComputation, new PostgresSubscriptionActivationReconciliationCaseRepository($connection),
            new TestSubscriptionActivationAudit($connection, false), new PostgresSubscriptionActivationTransaction($connection),
            new PostgresSubscriptionIntegrationOutboxRepository($connection, new SubscriptionIntegrationOutboxPersistenceMapper),
            new AnnualTermCalculator, new SubscriptionActivationRetryPolicy,
        );

        try {
            $service->execute($applicationId, $this->time());
            self::fail('Expected a subscription save failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('forced subscription save failure', $exception->getMessage());
        }

        self::assertSame(0, $this->connection()->table('subscriptions')->count());
        self::assertSame('processing', $this->connection()->table('subscription_activation_applications')->value('status'));
        self::assertSame(0, $this->connection()->table('subscription_activation_test_audits')->count());
        self::assertSame(0, $this->connection()->table('subscription_integration_outbox')->count());
    }

    public function test_outbox_insert_failure_rolls_back_the_subscription_and_application_completion(): void
    {
        [, $applicationId] = $this->service(false);
        $connection = $this->connection();
        $service = new ActivateSubscriptionFromVerifiedPaymentService(
            new PostgresSubscriptionActivationApplicationRepository($connection, new SubscriptionActivationApplicationPersistenceMapper),
            new PostgresSubscriptionActivationEvidenceRepository($connection),
            new PostgresSubscriptionRepository($connection, new SubscriptionPersistenceMapper), new TestEntitlementComputation,
            new PostgresSubscriptionActivationReconciliationCaseRepository($connection), new TestSubscriptionActivationAudit($connection, false),
            new PostgresSubscriptionActivationTransaction($connection),
            new FailingSubscriptionIntegrationOutboxRepository(new PostgresSubscriptionIntegrationOutboxRepository($connection, new SubscriptionIntegrationOutboxPersistenceMapper)),
            new AnnualTermCalculator, new SubscriptionActivationRetryPolicy,
        );

        try {
            $service->execute($applicationId, $this->time());
            self::fail('Expected an outbox insert failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('forced outbox insert failure', $exception->getMessage());
        }

        self::assertSame(0, $this->connection()->table('subscriptions')->count());
        self::assertSame('processing', $this->connection()->table('subscription_activation_applications')->value('status'));
        self::assertSame(0, $this->connection()->table('subscription_activation_test_audits')->count());
        self::assertSame(0, $this->connection()->table('subscription_integration_outbox')->count());
    }

    public function test_application_completion_failure_rolls_back_the_subscription_and_outbox_insert(): void
    {
        [, $applicationId] = $this->service(false);
        $connection = $this->connection();
        $service = new ActivateSubscriptionFromVerifiedPaymentService(
            new FailingCompletionApplicationRepository(new PostgresSubscriptionActivationApplicationRepository($connection, new SubscriptionActivationApplicationPersistenceMapper)),
            new PostgresSubscriptionActivationEvidenceRepository($connection),
            new PostgresSubscriptionRepository($connection, new SubscriptionPersistenceMapper), new TestEntitlementComputation,
            new PostgresSubscriptionActivationReconciliationCaseRepository($connection), new TestSubscriptionActivationAudit($connection, false),
            new PostgresSubscriptionActivationTransaction($connection),
            new PostgresSubscriptionIntegrationOutboxRepository($connection, new SubscriptionIntegrationOutboxPersistenceMapper),
            new AnnualTermCalculator, new SubscriptionActivationRetryPolicy,
        );

        try {
            $service->execute($applicationId, $this->time());
            self::fail('Expected an application completion failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('forced application completion failure', $exception->getMessage());
        }

        self::assertSame(0, $this->connection()->table('subscriptions')->count());
        self::assertSame('processing', $this->connection()->table('subscription_activation_applications')->value('status'));
        self::assertSame(0, $this->connection()->table('subscription_activation_test_audits')->count());
        self::assertSame(0, $this->connection()->table('subscription_integration_outbox')->count());
    }

    public function test_a_transient_failure_leaves_the_lease_intact_then_permits_reclaim_and_success_once_it_expires(): void
    {
        [, $applicationId] = $this->service(false);
        $connection = $this->connection();
        $failingService = new ActivateSubscriptionFromVerifiedPaymentService(
            new PostgresSubscriptionActivationApplicationRepository($connection, new SubscriptionActivationApplicationPersistenceMapper),
            new PostgresSubscriptionActivationEvidenceRepository($connection),
            new PostgresSubscriptionRepository($connection, new SubscriptionPersistenceMapper), new TestEntitlementComputation,
            new PostgresSubscriptionActivationReconciliationCaseRepository($connection), new TestSubscriptionActivationAudit($connection, true),
            new PostgresSubscriptionActivationTransaction($connection),
            new PostgresSubscriptionIntegrationOutboxRepository($connection, new SubscriptionIntegrationOutboxPersistenceMapper),
            new AnnualTermCalculator, new SubscriptionActivationRetryPolicy,
        );

        try {
            $failingService->execute($applicationId, $this->time());
            self::fail('Expected the first, transiently-failing attempt to throw.');
        } catch (RuntimeException) {
        }
        self::assertSame('processing', $this->connection()->table('subscription_activation_applications')->value('status'));

        // Immediately retrying while the 120-second lease is still active must
        // not falsely succeed — the row is still legitimately claimed.
        $successService = $this->realService();
        $successService->execute($applicationId, $this->time());
        self::assertSame('processing', $this->connection()->table('subscription_activation_applications')->value('status'), 'A retry inside the active lease window must not silently no-op as success.');
        self::assertSame(0, $this->connection()->table('subscriptions')->count());

        // Only once the lease has genuinely expired can a later attempt
        // reclaim the application and complete it.
        $successService->execute($applicationId, $this->time()->modify('+3 minutes'));
        self::assertSame('applied', $this->connection()->table('subscription_activation_applications')->value('status'));
        self::assertSame(1, $this->connection()->table('subscriptions')->count());
    }

    public function test_a_duplicate_execute_call_against_an_already_applied_application_is_a_safe_no_op(): void
    {
        [$service, $applicationId] = $this->service(false);
        $service->execute($applicationId, $this->time());
        self::assertSame('applied', $this->connection()->table('subscription_activation_applications')->value('status'));
        self::assertSame(1, $this->connection()->table('subscriptions')->count());
        self::assertSame(1, $this->connection()->table('subscription_integration_outbox')->count());
        self::assertSame(1, $this->connection()->table('subscription_activation_test_audits')->count());

        // A duplicate delivery of the same event, or a duplicate queue run of
        // the same job, must never create a second Subscription.
        $applications = new PostgresSubscriptionActivationApplicationRepository($this->connection(), new SubscriptionActivationApplicationPersistenceMapper);
        $duplicateApplication = $applications->register($this->uuid(20), $this->uuid(4), $this->uuid(3), $this->time());
        self::assertSame($applicationId, $duplicateApplication->id);

        $service->execute($applicationId, $this->time()->modify('+1 second'));

        self::assertSame('applied', $this->connection()->table('subscription_activation_applications')->value('status'));
        self::assertSame(1, $this->connection()->table('subscriptions')->count());
        self::assertSame(1, $this->connection()->table('subscription_integration_outbox')->count());
        self::assertSame(1, $this->connection()->table('subscription_activation_test_audits')->count());
    }

    public function test_a_mismatched_offer_tenant_fails_closed_with_no_partial_writes(): void
    {
        [$service, $applicationId] = $this->service(false, offerTenantId: $this->uuid(99));

        $service->execute($applicationId, $this->time());

        self::assertSame('quarantined', $this->connection()->table('subscription_activation_applications')->value('status'));
        self::assertSame('tenant_mismatch', $this->connection()->table('subscription_activation_applications')->value('result_code'));
        self::assertSame(0, $this->connection()->table('subscriptions')->count());
        self::assertSame(0, $this->connection()->table('subscription_integration_outbox')->count());
    }

    public function test_a_mismatched_clinic_registration_tenant_fails_closed_with_no_partial_writes(): void
    {
        [$service, $applicationId] = $this->service(false, clinicTenantId: $this->uuid(99));

        $service->execute($applicationId, $this->time());

        self::assertSame('quarantined', $this->connection()->table('subscription_activation_applications')->value('status'));
        self::assertSame('tenant_mismatch', $this->connection()->table('subscription_activation_applications')->value('result_code'));
        self::assertSame(0, $this->connection()->table('subscriptions')->count());
        self::assertSame(0, $this->connection()->table('subscription_integration_outbox')->count());
    }

    public function test_exhaust_marks_a_stuck_processing_application_with_an_audit_and_reconciliation_signal(): void
    {
        [$service, $applicationId] = $this->service(false);
        $applications = new PostgresSubscriptionActivationApplicationRepository($this->connection(), new SubscriptionActivationApplicationPersistenceMapper);
        $applications->claim($applicationId, $this->time(), 120);
        self::assertSame('processing', $this->connection()->table('subscription_activation_applications')->value('status'));

        $service->exhaust($applicationId, $this->time()->modify('+10 minutes'));

        self::assertSame('exhausted', $this->connection()->table('subscription_activation_applications')->value('status'));
        self::assertSame('exhausted', $this->connection()->table('subscription_activation_applications')->value('result_code'));
        self::assertSame('activation_retries_exhausted', $this->connection()->table('subscription_activation_applications')->value('safe_failure_label'));
        self::assertNull($this->connection()->table('subscription_activation_applications')->value('processing_claim_token'));
        self::assertSame(1, $this->connection()->table('subscription_activation_reconciliation_cases')->count());
        self::assertSame('activation_retries_exhausted', $this->connection()->table('subscription_activation_reconciliation_cases')->value('reason_code'));
        self::assertSame(1, $this->connection()->table('subscription_activation_test_audits')->count());
        self::assertSame('subscription.activation.exhausted', $this->connection()->table('subscription_activation_test_audits')->value('action'));
        self::assertSame(0, $this->connection()->table('subscriptions')->count());
    }

    public function test_exhaust_is_a_safe_no_op_once_the_application_already_succeeded(): void
    {
        [$service, $applicationId] = $this->service(false);
        $service->execute($applicationId, $this->time());
        self::assertSame('applied', $this->connection()->table('subscription_activation_applications')->value('status'));

        $service->exhaust($applicationId, $this->time()->modify('+10 minutes'));

        self::assertSame('applied', $this->connection()->table('subscription_activation_applications')->value('status'), 'exhaust() must never overwrite a real outcome.');
        self::assertSame('applied', $this->connection()->table('subscription_activation_applications')->value('result_code'));
        self::assertSame(0, $this->connection()->table('subscription_activation_reconciliation_cases')->count());
        self::assertSame(1, $this->connection()->table('subscription_activation_test_audits')->count());
    }

    public function test_exhaust_rolls_back_status_reconciliation_and_audit_when_audit_recording_fails(): void
    {
        [$service, $applicationId] = $this->service(true);

        try {
            $service->exhaust($applicationId, $this->time()->modify('+10 minutes'));
            self::fail('Expected audit failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('forced audit failure', $exception->getMessage());
        }

        self::assertSame('pending', $this->connection()->table('subscription_activation_applications')->value('status'));
        self::assertNull($this->connection()->table('subscription_activation_applications')->value('result_code'));
        self::assertNull($this->connection()->table('subscription_activation_applications')->value('safe_failure_label'));
        self::assertSame(0, $this->connection()->table('subscription_activation_reconciliation_cases')->count());
        self::assertSame(0, $this->connection()->table('subscription_activation_test_audits')->count());
    }

    /** @return array{ActivateSubscriptionFromVerifiedPaymentService,string} */
    private function service(bool $failAudit, ?string $offerTenantId = null, ?string $clinicTenantId = null): array
    {
        $this->fixtures($offerTenantId, $clinicTenantId);
        $connection = $this->connection();
        $applications = new PostgresSubscriptionActivationApplicationRepository($connection, new SubscriptionActivationApplicationPersistenceMapper);
        $application = $applications->register($this->uuid(20), $this->uuid(4), $this->uuid(3), $this->time());

        return [new ActivateSubscriptionFromVerifiedPaymentService(
            $applications, new PostgresSubscriptionActivationEvidenceRepository($connection),
            new PostgresSubscriptionRepository($connection, new SubscriptionPersistenceMapper), new TestEntitlementComputation,
            new PostgresSubscriptionActivationReconciliationCaseRepository($connection), new TestSubscriptionActivationAudit($connection, $failAudit),
            new PostgresSubscriptionActivationTransaction($connection),
            new PostgresSubscriptionIntegrationOutboxRepository($connection, new SubscriptionIntegrationOutboxPersistenceMapper),
            new AnnualTermCalculator, new SubscriptionActivationRetryPolicy,
        ), $application->id];
    }

    private function realService(): ActivateSubscriptionFromVerifiedPaymentService
    {
        $connection = $this->connection();

        return new ActivateSubscriptionFromVerifiedPaymentService(
            new PostgresSubscriptionActivationApplicationRepository($connection, new SubscriptionActivationApplicationPersistenceMapper),
            new PostgresSubscriptionActivationEvidenceRepository($connection),
            new PostgresSubscriptionRepository($connection, new SubscriptionPersistenceMapper), new TestEntitlementComputation,
            new PostgresSubscriptionActivationReconciliationCaseRepository($connection), new TestSubscriptionActivationAudit($connection, false),
            new PostgresSubscriptionActivationTransaction($connection),
            new PostgresSubscriptionIntegrationOutboxRepository($connection, new SubscriptionIntegrationOutboxPersistenceMapper),
            new AnnualTermCalculator, new SubscriptionActivationRetryPolicy,
        );
    }

    private function fixtures(?string $offerTenantId = null, ?string $clinicTenantId = null): void
    {
        $timestamp = $this->time()->format('Y-m-d H:i:s.uP');
        $this->connection()->table('payments')->insert(['id' => $this->uuid(4), 'commercial_offer_id' => $this->uuid(5), 'clinic_registration_id' => $this->uuid(10), 'tenant_id' => $this->uuid(3), 'amount_minor' => 12500, 'currency' => 'MYR', 'status' => 'succeeded']);
        $this->connection()->table('clinic_registrations')->insert(['id' => $this->uuid(10), 'reserved_tenant_id' => $clinicTenantId ?? $this->uuid(3)]);
        $this->connection()->table('commercial_offers')->insert(['id' => $this->uuid(5), 'tenant_id' => $offerTenantId ?? $this->uuid(3), 'clinic_registration_id' => $this->uuid(10), 'claimed_payment_id' => $this->uuid(4), 'plan_offering_id' => $this->uuid(11), 'plan_id' => $this->uuid(6), 'billing_cycle_id' => $this->uuid(7), 'billing_period_start' => '2026-07-01', 'billing_period_end' => '2027-06-30', 'total_amount_minor' => 12500, 'currency' => 'MYR', 'offering_configuration_version' => 'offer-v1', 'capability_configuration_reference' => 'capability-v1']);
        $this->connection()->table('payment_integration_outbox')->insert(['id' => $this->uuid(20), 'payment_id' => $this->uuid(4), 'event_type' => 'VerifiedPaymentSucceeded', 'event_version' => 1, 'created_at' => $timestamp]);
    }

    private function createEvidenceTables(): void
    {
        Schema::connection(self::CONNECTION)->create('payments', fn (Blueprint $t) => [$t->uuid('id')->primary(), $t->uuid('commercial_offer_id'), $t->uuid('clinic_registration_id'), $t->uuid('tenant_id')->nullable(), $t->unsignedBigInteger('amount_minor'), $t->char('currency', 3), $t->string('status', 32)]);
        Schema::connection(self::CONNECTION)->create('clinic_registrations', fn (Blueprint $t) => [$t->uuid('id')->primary(), $t->uuid('reserved_tenant_id')->nullable()]);
        Schema::connection(self::CONNECTION)->create('commercial_offers', fn (Blueprint $t) => [$t->uuid('id')->primary(), $t->uuid('tenant_id')->nullable(), $t->uuid('clinic_registration_id'), $t->uuid('claimed_payment_id')->nullable(), $t->string('plan_offering_id'), $t->string('plan_id'), $t->string('billing_cycle_id'), $t->date('billing_period_start'), $t->date('billing_period_end'), $t->unsignedBigInteger('total_amount_minor'), $t->char('currency', 3), $t->string('offering_configuration_version'), $t->string('capability_configuration_reference')]);
        Schema::connection(self::CONNECTION)->create('payment_integration_outbox', fn (Blueprint $t) => [$t->uuid('id')->primary(), $t->uuid('payment_id'), $t->string('event_type'), $t->unsignedInteger('event_version'), $t->timestampTz('created_at')]);
        Schema::connection(self::CONNECTION)->create('subscription_activation_test_audits', fn (Blueprint $t) => [$t->uuid('id')->primary(), $t->string('action')]);
    }

    private function dropTables(): void
    {
        foreach (['subscription_integration_outbox', 'subscription_activation_reconciliation_cases', 'subscription_activation_applications', 'subscriptions', 'subscription_activation_test_audits', 'payment_integration_outbox', 'commercial_offers', 'clinic_registrations', 'payments'] as $table) {
            Schema::connection(self::CONNECTION)->dropIfExists($table);
        }
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

final readonly class TestEntitlementComputation implements SubscriptionEntitlementComputationInterface
{
    public function compute(ResolvedSubscriptionOfferingData $resolvedOffering): ComputedSubscriptionEntitlementData
    {
        return new ComputedSubscriptionEntitlementData($resolvedOffering->planId, $resolvedOffering->billingOptionId, 'capability-v1', ['appointments.manage']);
    }
}

final readonly class TestSubscriptionActivationAudit implements SubscriptionActivationAuditInterface
{
    public function __construct(private ConnectionInterface $connection, private bool $fail) {}

    public function record(string $action, string $applicationId, string $subscriptionId, string $paymentId, string $tenantId, SubscriptionActivationApplicationResultCode $resultCode, DateTimeImmutable $occurredAt): void
    {
        $this->connection->table('subscription_activation_test_audits')->insert(['id' => $applicationId, 'action' => $action]);
        if ($this->fail) {
            throw new RuntimeException('forced audit failure');
        }
    }
}

final readonly class FailingSubscriptionRepository implements SubscriptionRepositoryInterface
{
    public function __construct(private SubscriptionRepositoryInterface $inner) {}

    public function find(SubscriptionId $id): ?Subscription
    {
        return $this->inner->find($id);
    }

    public function findByTenantId(TenantId $tenantId): ?Subscription
    {
        return $this->inner->findByTenantId($tenantId);
    }

    public function findByPaymentId(PaymentId $paymentId): ?Subscription
    {
        return $this->inner->findByPaymentId($paymentId);
    }

    public function save(Subscription $subscription): void
    {
        throw new RuntimeException('forced subscription save failure');
    }
}

final readonly class FailingSubscriptionIntegrationOutboxRepository implements SubscriptionIntegrationOutboxRepositoryInterface
{
    public function __construct(private SubscriptionIntegrationOutboxRepositoryInterface $inner) {}

    public function add(SubscriptionActivatedIntegrationEvent $event): void
    {
        throw new RuntimeException('forced outbox insert failure');
    }

    public function pending(DateTimeImmutable $availableAt, int $limit = 100): array
    {
        return $this->inner->pending($availableAt, $limit);
    }

    public function claimNext(DateTimeImmutable $now, int $leaseSeconds = 120): ?SubscriptionIntegrationOutboxClaim
    {
        return $this->inner->claimNext($now, $leaseSeconds);
    }

    public function completeDispatch(string $eventId, string $leaseToken, DateTimeImmutable $dispatchedAt): bool
    {
        return $this->inner->completeDispatch($eventId, $leaseToken, $dispatchedAt);
    }

    public function releaseForRetry(string $eventId, string $leaseToken, DateTimeImmutable $nextRetryAt, string $safeFailureLabel, DateTimeImmutable $now): bool
    {
        return $this->inner->releaseForRetry($eventId, $leaseToken, $nextRetryAt, $safeFailureLabel, $now);
    }
}

final readonly class FailingCompletionApplicationRepository implements SubscriptionActivationApplicationRepositoryInterface
{
    public function __construct(private SubscriptionActivationApplicationRepositoryInterface $inner) {}

    public function register(string $sourceEventId, string $paymentId, string $tenantId, DateTimeImmutable $now): SubscriptionActivationApplication
    {
        return $this->inner->register($sourceEventId, $paymentId, $tenantId, $now);
    }

    public function claim(string $applicationId, DateTimeImmutable $now, int $leaseSeconds): ?SubscriptionActivationApplication
    {
        return $this->inner->claim($applicationId, $now, $leaseSeconds);
    }

    public function find(string $applicationId): ?SubscriptionActivationApplication
    {
        return $this->inner->find($applicationId);
    }

    public function complete(string $applicationId, string $claimToken, SubscriptionActivationApplicationStatus $status, SubscriptionActivationApplicationResultCode $resultCode, DateTimeImmutable $now, ?DateTimeImmutable $nextAttemptAt = null): bool
    {
        throw new RuntimeException('forced application completion failure');
    }

    public function markExhausted(string $applicationId, string $safeFailureLabel, DateTimeImmutable $now): bool
    {
        return $this->inner->markExhausted($applicationId, $safeFailureLabel, $now);
    }
}
