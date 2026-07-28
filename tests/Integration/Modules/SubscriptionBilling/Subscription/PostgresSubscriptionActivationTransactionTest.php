<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\SubscriptionBilling\Subscription;

use App\Modules\SubscriptionBilling\Application\Subscription\ActivateSubscriptionFromVerifiedPaymentService;
use App\Modules\SubscriptionBilling\Application\Subscription\AnnualTermCalculator;
use App\Modules\SubscriptionBilling\Application\Subscription\SubscriptionActivationRetryPolicy;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\ResolvedSubscriptionOfferingData;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\ComputedSubscriptionEntitlementData;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\SubscriptionEntitlementComputationInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationApplicationResultCode;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationAuditInterface;
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
        foreach (['2026_07_27_000001_create_subscriptions_table.php', '2026_07_28_000001_create_subscription_activation_tables.php', '2026_07_29_000001_create_subscription_integration_outbox.php'] as $file) {
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

    /** @return array{ActivateSubscriptionFromVerifiedPaymentService,string} */
    private function service(bool $failAudit): array
    {
        $this->fixtures();
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

    private function fixtures(): void
    {
        $timestamp = $this->time()->format('Y-m-d H:i:s.uP');
        $this->connection()->table('payments')->insert(['id' => $this->uuid(4), 'commercial_offer_id' => $this->uuid(5), 'clinic_registration_id' => $this->uuid(10), 'tenant_id' => $this->uuid(3), 'amount_minor' => 12500, 'currency' => 'MYR', 'status' => 'succeeded']);
        $this->connection()->table('clinic_registrations')->insert(['id' => $this->uuid(10), 'reserved_tenant_id' => $this->uuid(3)]);
        $this->connection()->table('commercial_offers')->insert(['id' => $this->uuid(5), 'tenant_id' => $this->uuid(3), 'clinic_registration_id' => $this->uuid(10), 'claimed_payment_id' => $this->uuid(4), 'plan_offering_id' => $this->uuid(11), 'plan_id' => $this->uuid(6), 'billing_cycle_id' => $this->uuid(7), 'billing_period_start' => '2026-07-01', 'billing_period_end' => '2027-06-30', 'total_amount_minor' => 12500, 'currency' => 'MYR', 'offering_configuration_version' => 'offer-v1', 'capability_configuration_reference' => 'capability-v1']);
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
