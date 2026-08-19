<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\SubscriptionBilling\Subscription;

use App\Modules\SubscriptionBilling\Application\Subscription\ActivateSubscriptionFromVerifiedPaymentService;
use App\Modules\SubscriptionBilling\Application\Subscription\AnnualTermCalculator;
use App\Modules\SubscriptionBilling\Application\Subscription\SubscriptionActivationRetryPolicy;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\ResolvedSubscriptionOfferingData;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\ComputedSubscriptionEntitlementData;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\SubscriptionEntitlementComputationInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentIntegrationOutboxEvent;
use App\Modules\SubscriptionBilling\Contracts\Repositories\SubscriptionRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivatedIntegrationEvent;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationApplicationResultCode;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationAuditInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationEvidence;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationEvidenceRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationJobDispatcherInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationReconciliationCaseRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationTransactionInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionIntegrationOutboxClaim;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionIntegrationOutboxRepositoryInterface;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\Subscription;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\PaymentId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\SubscriptionId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\TenantId;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\SubscriptionActivationApplicationPersistenceMapper;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresSubscriptionActivationApplicationRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Subscription\HandleVerifiedPaymentSucceededForSubscriptionActivation;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

final class PostgresVerifiedPaymentSucceededSubscriptionActivationListenerTest extends TestCase
{
    private const string CONNECTION = 'verified_payment_activation_listener_postgres';

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
        Schema::connection(self::CONNECTION)->create('payments', fn (Blueprint $t) => [$t->uuid('id')->primary(), $t->uuid('tenant_id')->nullable()]);
        Schema::connection(self::CONNECTION)->create('subscription_renewals', fn (Blueprint $t) => [$t->uuid('id')->primary(), $t->uuid('payment_id')->nullable()->unique()]);
        $migration = require base_path('database/migrations/subscription_billing/2026_07_28_000001_create_subscription_activation_tables.php');
        self::assertInstanceOf(Migration::class, $migration);
        $migration->up();
        $this->migrations[] = $migration;
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

    public function test_registers_and_dispatches_for_a_genuine_initial_acquisition_payment(): void
    {
        $paymentId = $this->uuid(1);
        $tenantId = $this->uuid(2);
        $this->connection()->table('payments')->insert(['id' => $paymentId, 'tenant_id' => $tenantId]);

        $dispatched = [];
        $listener = $this->listener($dispatched);
        $listener->handle($this->event($paymentId));

        $application = $this->connection()->table('subscription_activation_applications')->where('payment_id', $paymentId)->first();
        self::assertIsObject($application);
        self::assertSame($tenantId, $application->tenant_id);
        self::assertCount(1, $dispatched);
        self::assertSame((string) $application->id, $dispatched[0]);
    }

    public function test_duplicate_delivery_reuses_one_activation_application_and_safe_job_identity(): void
    {
        $paymentId = $this->uuid(1);
        $tenantId = $this->uuid(2);
        $this->connection()->table('payments')->insert(['id' => $paymentId, 'tenant_id' => $tenantId]);

        $dispatched = [];
        $listener = $this->listener($dispatched);
        $event = $this->event($paymentId);
        $listener->handle($event);
        $listener->handle($event);

        self::assertSame(1, $this->connection()->table('subscription_activation_applications')->count());
        self::assertCount(2, $dispatched);
        self::assertSame($dispatched[0], $dispatched[1]);
    }

    public function test_skips_registration_for_a_renewal_payment(): void
    {
        $paymentId = $this->uuid(1);
        $tenantId = $this->uuid(2);
        $this->connection()->table('payments')->insert(['id' => $paymentId, 'tenant_id' => $tenantId]);
        $this->connection()->table('subscription_renewals')->insert(['id' => $this->uuid(3), 'payment_id' => $paymentId]);

        $dispatched = [];
        $listener = $this->listener($dispatched);
        $listener->handle($this->event($paymentId));

        self::assertSame(0, $this->connection()->table('subscription_activation_applications')->count());
        self::assertCount(0, $dispatched);
    }

    public function test_ignores_events_of_a_different_type_or_version(): void
    {
        $paymentId = $this->uuid(1);
        $this->connection()->table('payments')->insert(['id' => $paymentId, 'tenant_id' => $this->uuid(2)]);

        $dispatched = [];
        $listener = $this->listener($dispatched);
        $listener->handle($this->event($paymentId, type: 'PaymentSucceeded'));
        $listener->handle($this->event($paymentId, eventVersion: 2));

        self::assertSame(0, $this->connection()->table('subscription_activation_applications')->count());
        self::assertCount(0, $dispatched);
    }

    public function test_skips_when_the_payment_has_no_tenant_id(): void
    {
        $paymentId = $this->uuid(1);
        $this->connection()->table('payments')->insert(['id' => $paymentId, 'tenant_id' => null]);

        $dispatched = [];
        $listener = $this->listener($dispatched);
        $listener->handle($this->event($paymentId));

        self::assertSame(0, $this->connection()->table('subscription_activation_applications')->count());
        self::assertCount(0, $dispatched);
    }

    /** @param list<string> $dispatched */
    private function listener(array &$dispatched): HandleVerifiedPaymentSucceededForSubscriptionActivation
    {
        return new HandleVerifiedPaymentSucceededForSubscriptionActivation(
            $this->connection(),
            new ActivateSubscriptionFromVerifiedPaymentService(
                new PostgresSubscriptionActivationApplicationRepository($this->connection(), new SubscriptionActivationApplicationPersistenceMapper),
                $this->neverCalled(SubscriptionActivationEvidenceRepositoryInterface::class),
                $this->neverCalled(SubscriptionRepositoryInterface::class),
                $this->neverCalled(SubscriptionEntitlementComputationInterface::class),
                $this->neverCalled(SubscriptionActivationReconciliationCaseRepositoryInterface::class),
                $this->neverCalled(SubscriptionActivationAuditInterface::class),
                $this->neverCalled(SubscriptionActivationTransactionInterface::class),
                $this->neverCalled(SubscriptionIntegrationOutboxRepositoryInterface::class),
                new AnnualTermCalculator,
                new SubscriptionActivationRetryPolicy,
            ),
            $this->recordingDispatcher($dispatched),
        );
    }

    /** @template T @param class-string<T> $interface @return T */
    private function neverCalled(string $interface): object
    {
        return new class($interface) implements SubscriptionActivationAuditInterface, SubscriptionActivationEvidenceRepositoryInterface, SubscriptionActivationReconciliationCaseRepositoryInterface, SubscriptionActivationTransactionInterface, SubscriptionEntitlementComputationInterface, SubscriptionIntegrationOutboxRepositoryInterface, SubscriptionRepositoryInterface
        {
            public function __construct(private string $interface) {}

            private function unexpected(string $name): never
            {
                throw new RuntimeException("Unexpected call to {$this->interface}::{$name}() — register() must not touch this collaborator.");
            }

            public function loadForUpdate(string $sourceEventId, string $paymentId): ?SubscriptionActivationEvidence
            {
                $this->unexpected(__FUNCTION__);
            }

            public function find(SubscriptionId $id): ?Subscription
            {
                $this->unexpected(__FUNCTION__);
            }

            public function findByTenantId(TenantId $tenantId): ?Subscription
            {
                $this->unexpected(__FUNCTION__);
            }

            public function findByPaymentId(PaymentId $paymentId): ?Subscription
            {
                $this->unexpected(__FUNCTION__);
            }

            public function save(Subscription $subscription): void
            {
                $this->unexpected(__FUNCTION__);
            }

            public function compute(ResolvedSubscriptionOfferingData $resolvedOffering): ComputedSubscriptionEntitlementData
            {
                $this->unexpected(__FUNCTION__);
            }

            public function open(string $applicationId, string $paymentId, string $tenantId, string $reasonCode, DateTimeImmutable $openedAt): string
            {
                $this->unexpected(__FUNCTION__);
            }

            public function record(string $action, string $applicationId, string $subscriptionId, string $paymentId, string $tenantId, SubscriptionActivationApplicationResultCode $resultCode, DateTimeImmutable $occurredAt): void
            {
                $this->unexpected(__FUNCTION__);
            }

            public function run(callable $operation): mixed
            {
                $this->unexpected(__FUNCTION__);
            }

            public function add(SubscriptionActivatedIntegrationEvent $event): void
            {
                $this->unexpected(__FUNCTION__);
            }

            /** @return list<SubscriptionActivatedIntegrationEvent> */
            public function pending(DateTimeImmutable $availableAt, int $limit = 100): array
            {
                $this->unexpected(__FUNCTION__);
            }

            public function claimNext(DateTimeImmutable $now, int $leaseSeconds = 120): ?SubscriptionIntegrationOutboxClaim
            {
                $this->unexpected(__FUNCTION__);
            }

            public function completeDispatch(string $eventId, string $leaseToken, DateTimeImmutable $dispatchedAt): bool
            {
                $this->unexpected(__FUNCTION__);
            }

            public function releaseForRetry(string $eventId, string $leaseToken, DateTimeImmutable $nextRetryAt, string $safeFailureLabel, DateTimeImmutable $now): bool
            {
                $this->unexpected(__FUNCTION__);
            }
        };
    }

    /** @param list<string> $dispatched */
    private function recordingDispatcher(array &$dispatched): SubscriptionActivationJobDispatcherInterface
    {
        return new class($dispatched) implements SubscriptionActivationJobDispatcherInterface
        {
            /** @param list<string> $dispatched */
            public function __construct(private array &$dispatched) {}

            public function dispatch(string $applicationId, int $delaySeconds = 0): void
            {
                $this->dispatched[] = $applicationId;
            }
        };
    }

    private function event(string $paymentId, string $type = 'VerifiedPaymentSucceeded', int $eventVersion = 1): PaymentIntegrationOutboxEvent
    {
        return new PaymentIntegrationOutboxEvent($this->uuid(9), $type, $eventVersion, $paymentId, ['payment_id' => $paymentId], new DateTimeImmutable('2026-07-25T00:00:00Z'));
    }

    private function dropTables(): void
    {
        foreach (['subscription_activation_reconciliation_cases', 'subscription_activation_applications', 'subscription_renewals', 'payments'] as $table) {
            Schema::connection(self::CONNECTION)->dropIfExists($table);
        }
    }

    private function connection(): ConnectionInterface
    {
        self::assertNotNull($this->connection);

        return $this->connection;
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
