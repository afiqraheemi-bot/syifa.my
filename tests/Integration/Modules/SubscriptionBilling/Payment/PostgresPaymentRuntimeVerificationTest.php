<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\SubscriptionBilling\Payment;

use App\Modules\AcquisitionOffer\Application\Audit\CommercialOfferAuditTrail;
use App\Modules\AcquisitionOffer\Application\ClaimCommercialOfferService as CommercialClaimCommercialOfferService;
use App\Modules\AcquisitionOffer\Application\CommercialOfferDataAssembler;
use App\Modules\AcquisitionOffer\Application\TrustedCommercialOfferConsumers;
use App\Modules\AcquisitionOffer\Contracts\Events\CommercialOfferEventPublisherInterface;
use App\Modules\AcquisitionOffer\Domain\CommercialOffer;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\CheckoutSnapshot;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\ClinicRegistrationReference;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\CommercialOfferId;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\CommercialOfferLineItem;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\OfferExpiry;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\PlatformIdentityReference;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\PriceSnapshot;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\TenantId as CommercialTenantId;
use App\Modules\AcquisitionOffer\Infrastructure\Persistence\Mappers\CommercialOfferPersistenceMapper;
use App\Modules\AcquisitionOffer\Infrastructure\Persistence\Repositories\PostgresCommercialOfferRepository;
use App\Modules\AcquisitionOffer\Infrastructure\Transactions\PostgresCommercialTransaction;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipal;
use App\Modules\PlatformAdministration\Domain\AuditEntry\AuditEntry;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditEntryId;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use App\Modules\SubscriptionBilling\Application\Payment\ClaimCommercialOfferService as PaymentClaimCommercialOfferService;
use App\Modules\SubscriptionBilling\Application\Payment\CreateInitialAcquisitionPaymentService;
use App\Modules\SubscriptionBilling\Application\Payment\CreatePaymentService;
use App\Modules\SubscriptionBilling\Application\Payment\PaymentDataAssembler;
use App\Modules\SubscriptionBilling\Application\Payment\PaymentIdentifierGeneratorInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\CreateInitialAcquisitionPaymentCommand;
use App\Modules\SubscriptionBilling\Contracts\Payment\CreatePaymentCommand;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentAuditInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\ExpiryAuthority;
use App\Modules\SubscriptionBilling\Contracts\Renewal\PaymentSession;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RedirectAction;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Payment;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\IdempotencyKey;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentAmount;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentCurrency;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentReference;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\TenantId;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\PostgresInitialAcquisitionCheckoutStore;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\PostgresPaymentTransaction;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\PostgresPublicInitialAcquisitionStatusReadAdapter;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\PaymentPersistenceMapper;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresPaymentRepository;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;
use Throwable;

final class PostgresPaymentRuntimeVerificationTest extends TestCase
{
    private const string CONNECTION_NAME = 'payment_runtime_postgres_integration';

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
        $this->connection?->table('initial_acquisition_checkout_sessions')->delete();
        $this->connection?->table('payment_attempts')->delete();
        $this->connection?->table('payments')->delete();
        $this->connection?->table('commercial_offer_line_items')->delete();
        $this->connection?->table('commercial_offers')->delete();

        foreach (array_reverse($this->migrations) as $migration) {
            $migration->down();
        }

        DB::purge(self::CONNECTION_NAME);
        parent::tearDown();
    }

    public function test_payment_creation_and_commercial_offer_claim_commit_together(): void
    {
        $this->commercialOffers()->save($this->offer());
        $paymentAudit = new RecordingPaymentAudit;
        $events = new AfterCommitCommercialEventPublisher($this->connection());

        $payment = $this->createPaymentService($paymentAudit, $events)->execute(
            new PlatformPrincipal($this->uuid(2), 'super_admin', 'Afiq'),
            new CreatePaymentCommand($this->uuid(11), 'idem-1', $this->time(), $this->uuid(90)),
        );

        $offer = $this->commercialOffers()->find(new CommercialOfferId($this->uuid(11)));
        self::assertNotNull($offer);
        self::assertSame('claimed', $offer->status->value);
        self::assertSame($payment->paymentId, $offer->claimedPaymentId);
        self::assertSame(1, $this->connection()->table('payments')->where('id', $payment->paymentId)->count());
        self::assertSame(['payment.create'], $paymentAudit->actions);
        self::assertCount(1, $events->published);
    }

    public function test_initial_acquisition_payment_persists_registration_ownership_and_reuses_payment(): void
    {
        $this->commercialOffers()->save($this->acquisitionOffer());
        $events = new AfterCommitCommercialEventPublisher($this->connection());
        $commercialClaim = new CommercialClaimCommercialOfferService(
            $this->commercialOffers(),
            new CommercialOfferDataAssembler,
            new CommercialOfferAuditTrail(new RecordingAuditEntryRecorder),
            $events,
            new TrustedCommercialOfferConsumers(['payment']),
            new PostgresCommercialTransaction($this->connection()),
        );
        $service = new CreateInitialAcquisitionPaymentService(
            new FixedPaymentIdentifierGenerator($this->uuid(25)),
            $commercialClaim,
            new PaymentClaimCommercialOfferService($commercialClaim),
            $this->payments(),
            new PaymentDataAssembler,
            new RecordingPaymentAudit,
            new PostgresPaymentTransaction($this->connection()),
        );
        $command = new CreateInitialAcquisitionPaymentCommand(
            $this->uuid(3),
            $this->uuid(12),
            $this->uuid(6),
            $this->time(),
            $this->uuid(95),
        );

        $created = $service->execute($command);
        $reused = $service->execute($command);

        self::assertSame($created->paymentId, $reused->paymentId);
        self::assertSame(1, $this->connection()->table('payments')->count());
        self::assertSame(0, $this->connection()->table('payment_attempts')->count());
        $row = $this->connection()->table('payments')->where('id', $created->paymentId)->first();
        self::assertNotNull($row);
        self::assertNull($row->platform_identity_id);
        self::assertSame($this->uuid(3), $row->clinic_registration_id);
        self::assertSame($this->uuid(6), $row->tenant_id);
        self::assertSame(3000, $row->amount_minor);
        self::assertSame('MYR', $row->currency);
    }

    public function test_initial_acquisition_checkout_session_is_persisted_and_reused(): void
    {
        $this->commercialOffers()->save($this->acquisitionOffer());
        $payment = Payment::createInitialAcquisition(
            new PaymentId($this->uuid(25)),
            new PaymentReference($this->uuid(12)),
            new PaymentReference($this->uuid(3)),
            new TenantId($this->uuid(6)),
            new PaymentAmount(3000),
            new PaymentCurrency('MYR'),
            new IdempotencyKey('acquisition-session-test'),
            $this->time(),
        );
        $this->payments()->save($payment);
        $store = new PostgresInitialAcquisitionCheckoutStore($this->connection());
        $pending = $store->begin(
            $this->uuid(3),
            $this->uuid(12),
            $this->uuid(25),
            $this->time()->modify('+30 minutes'),
            $this->time(),
        );
        $session = new PaymentSession(
            'session-1',
            new RedirectAction('https://toyyibpay.com/bill-code-1'),
            $this->time()->modify('+30 minutes'),
            ExpiryAuthority::CommercialOffer,
        );

        $ready = $store->sessionReady($pending->applicationId, $this->uuid(25), $session, $this->time());
        $reused = $store->begin(
            $this->uuid(3),
            $this->uuid(12),
            $this->uuid(25),
            $this->time()->modify('+30 minutes'),
            $this->time(),
        );

        self::assertSame('session_ready', $ready->stage);
        self::assertSame($session->sessionId, $reused->session?->sessionId);
        self::assertSame(1, $this->connection()->table('initial_acquisition_checkout_sessions')->count());

        $status = (new PostgresPublicInitialAcquisitionStatusReadAdapter($this->connection()))
            ->forRegistration($this->uuid(3));
        self::assertNotNull($status);
        self::assertSame('draft', $status->paymentStatus);
        self::assertSame(3000, $status->amountMinor);
        self::assertSame('MYR', $status->currency);
        self::assertNull(
            (new PostgresPublicInitialAcquisitionStatusReadAdapter($this->connection()))
                ->forRegistration($this->uuid(99)),
        );
    }

    public function test_payment_insert_failure_after_claim_begins_rolls_back_claim(): void
    {
        $this->commercialOffers()->save($this->offer());
        $events = new AfterCommitCommercialEventPublisher($this->connection());
        $this->connection()->unprepared(<<<'SQL'
            CREATE FUNCTION syifa_test_reject_payment_insert() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'forced payment insert failure';
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER syifa_test_reject_payment_insert_trigger
            BEFORE INSERT ON payments
            FOR EACH ROW EXECUTE FUNCTION syifa_test_reject_payment_insert();
            SQL);

        try {
            try {
                $this->createPaymentService(new RecordingPaymentAudit, $events)->execute(
                    new PlatformPrincipal($this->uuid(2), 'super_admin', 'Afiq'),
                    new CreatePaymentCommand($this->uuid(11), 'idem-1', $this->time(), $this->uuid(90)),
                );
                self::fail('Payment insert failure should abort the whole create flow.');
            } catch (QueryException) {
                $offer = $this->commercialOffers()->find(new CommercialOfferId($this->uuid(11)));
                self::assertNotNull($offer);
                self::assertSame('prepared', $offer->status->value);
                self::assertNull($offer->claimedPaymentId);
                self::assertSame(0, $this->connection()->table('payments')->count());
                self::assertSame([], $events->published);
            }
        } finally {
            $this->connection()->unprepared('DROP TRIGGER IF EXISTS syifa_test_reject_payment_insert_trigger ON payments');
            $this->connection()->unprepared('DROP FUNCTION IF EXISTS syifa_test_reject_payment_insert()');
        }
    }

    public function test_commercial_offer_claim_failure_rolls_back_payment_creation(): void
    {
        $this->commercialOffers()->save($this->offer());
        $this->connection()->unprepared(<<<'SQL'
            CREATE FUNCTION syifa_test_reject_offer_claim() RETURNS trigger AS $$
            BEGIN
                IF NEW.status = 'claimed' THEN
                    RAISE EXCEPTION 'forced claim failure';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER syifa_test_reject_offer_claim_trigger
            BEFORE UPDATE ON commercial_offers
            FOR EACH ROW EXECUTE FUNCTION syifa_test_reject_offer_claim();
            SQL);

        try {
            try {
                $this->createPaymentService(new RecordingPaymentAudit, new AfterCommitCommercialEventPublisher($this->connection()))->execute(
                    new PlatformPrincipal($this->uuid(2), 'super_admin', 'Afiq'),
                    new CreatePaymentCommand($this->uuid(11), 'idem-1', $this->time(), $this->uuid(90)),
                );
                self::fail('Commercial Offer claim failure should abort Payment creation.');
            } catch (QueryException) {
                self::assertSame(0, $this->connection()->table('payments')->count());
            }
        } finally {
            $this->connection()->unprepared('DROP TRIGGER IF EXISTS syifa_test_reject_offer_claim_trigger ON commercial_offers');
            $this->connection()->unprepared('DROP FUNCTION IF EXISTS syifa_test_reject_offer_claim()');
        }
    }

    public function test_payment_audit_failure_rolls_back_payment_and_claim(): void
    {
        $this->commercialOffers()->save($this->offer());

        try {
            $this->createPaymentService(new FailingPaymentAudit, new AfterCommitCommercialEventPublisher($this->connection()))->execute(
                new PlatformPrincipal($this->uuid(2), 'super_admin', 'Afiq'),
                new CreatePaymentCommand($this->uuid(11), 'idem-1', $this->time(), $this->uuid(90)),
            );
            self::fail('Payment audit failure should abort Payment creation.');
        } catch (RuntimeException) {
            $offer = $this->commercialOffers()->find(new CommercialOfferId($this->uuid(11)));
            self::assertNotNull($offer);
            self::assertSame('prepared', $offer->status->value);
            self::assertNull($offer->claimedPaymentId);
            self::assertSame(0, $this->connection()->table('payments')->count());
        }
    }

    public function test_two_processes_cannot_create_two_payments_for_one_commercial_offer(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required for process-level concurrency verification.');
        }

        $this->commercialOffers()->save($this->offer());
        $workspace = sys_get_temp_dir().'/syifa-payment-concurrency-'.bin2hex(random_bytes(4));
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
            self::assertSame(1, count(array_filter($results, static fn (array $result): bool => $result['status'] === 'created')));
            self::assertSame(1, count(array_filter($results, static fn (array $result): bool => $result['status'] === 'rejected')));
            self::assertSame(1, $this->connection()->table('payments')->count());
            $offer = $this->commercialOffers()->find(new CommercialOfferId($this->uuid(11)));
            self::assertNotNull($offer);
            self::assertSame('claimed', $offer->status->value);
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

        try {
            $payment = $this->createPaymentService(
                new RecordingPaymentAudit,
                new AfterCommitCommercialEventPublisher($this->connection()),
                $this->uuid(20 + $childNumber),
            )->execute(
                new PlatformPrincipal($this->uuid(2), 'super_admin', 'Afiq'),
                new CreatePaymentCommand($this->uuid(11), 'idem-'.$childNumber, $this->time(), $this->uuid(90 + $childNumber)),
            );
            file_put_contents($workspace.'/result-'.$childNumber.'.json', json_encode([
                'status' => 'created',
                'payment_id' => $payment->paymentId,
            ], JSON_THROW_ON_ERROR));
        } catch (Throwable $throwable) {
            file_put_contents($workspace.'/result-'.$childNumber.'.json', json_encode([
                'status' => 'rejected',
                'error' => $throwable::class,
            ], JSON_THROW_ON_ERROR));
        }
    }

    private function createPaymentService(
        PaymentAuditInterface $paymentAudit,
        CommercialOfferEventPublisherInterface $events,
        ?string $paymentId = null,
    ): CreatePaymentService {
        $commercialClaim = new CommercialClaimCommercialOfferService(
            $this->commercialOffers(),
            new CommercialOfferDataAssembler,
            new CommercialOfferAuditTrail(new RecordingAuditEntryRecorder),
            $events,
            new TrustedCommercialOfferConsumers(['payment']),
            new PostgresCommercialTransaction($this->connection()),
        );

        return new CreatePaymentService(
            new FixedPaymentIdentifierGenerator($paymentId ?? $this->uuid(20)),
            $commercialClaim,
            new PaymentClaimCommercialOfferService($commercialClaim),
            $this->payments(),
            new PaymentDataAssembler,
            $paymentAudit,
            new PostgresPaymentTransaction($this->connection()),
        );
    }

    private function commercialOffers(): PostgresCommercialOfferRepository
    {
        return new PostgresCommercialOfferRepository($this->connection(), new CommercialOfferPersistenceMapper);
    }

    private function payments(): PostgresPaymentRepository
    {
        return new PostgresPaymentRepository($this->connection(), new PaymentPersistenceMapper);
    }

    private function dropTables(): void
    {
        Schema::connection(self::CONNECTION_NAME)->dropIfExists('payment_attempts');
        Schema::connection(self::CONNECTION_NAME)->dropIfExists('initial_acquisition_checkout_sessions');
        Schema::connection(self::CONNECTION_NAME)->dropIfExists('payments');
        Schema::connection(self::CONNECTION_NAME)->dropIfExists('commercial_offer_line_items');
        Schema::connection(self::CONNECTION_NAME)->dropIfExists('commercial_offers');
    }

    private function migrate(): void
    {
        foreach ([
            'database/migrations/acquisition_offer/2026_07_21_000001_create_commercial_offer_tables.php',
            'database/migrations/subscription_billing/2026_07_21_000002_create_payment_core_tables.php',
            'database/migrations/acquisition_offer/2026_07_26_000001_add_tenant_id_to_commercial_offers.php',
            'database/migrations/subscription_billing/2026_07_26_000001_add_tenant_id_to_payments.php',
            'database/migrations/acquisition_offer/2026_07_30_000001_add_renewal_offer_provenance.php',
            'database/migrations/acquisition_offer/2026_08_28_000001_correct_initial_commercial_offer_ownership.php',
            'database/migrations/subscription_billing/2026_08_29_000001_support_initial_acquisition_payment_ownership.php',
            'database/migrations/subscription_billing/2026_08_30_000001_create_initial_acquisition_checkout_sessions.php',
        ] as $path) {
            $migration = require base_path($path);
            self::assertInstanceOf(Migration::class, $migration);
            $migration->up();
            $this->migrations[] = $migration;
        }
    }

    private function offer(): CommercialOffer
    {
        return CommercialOffer::prepare(
            new CommercialOfferId($this->uuid(11)),
            new PlatformIdentityReference($this->uuid(2)),
            new ClinicRegistrationReference($this->uuid(3)),
            new CommercialTenantId($this->uuid(6)),
            new CheckoutSnapshot(
                'offering-basic-monthly',
                'plan-basic',
                'monthly',
                '2026-07-21',
                '2026-08-20',
                'catalogue-v1',
                'capability-v1',
                [new CommercialOfferLineItem(
                    'plan_offering',
                    'offering-basic-monthly',
                    'Basic — Monthly',
                    1,
                    new PriceSnapshot(3000, 'MYR'),
                    new PriceSnapshot(3000, 'MYR'),
                    'catalogue-v1',
                )],
                new PriceSnapshot(3000, 'MYR'),
                new PriceSnapshot(3000, 'MYR'),
            ),
            OfferExpiry::fromPreparedAt($this->time(), 30),
            $this->time(),
            $this->uuid(91),
        );
    }

    private function acquisitionOffer(): CommercialOffer
    {
        return CommercialOffer::prepareForClinicRegistration(
            new CommercialOfferId($this->uuid(12)),
            new ClinicRegistrationReference($this->uuid(3)),
            new CommercialTenantId($this->uuid(6)),
            $this->offer()->checkoutSnapshot,
            OfferExpiry::fromPreparedAt($this->time(), 30),
            $this->time(),
            $this->uuid(96),
        );
    }

    private function connection(): ConnectionInterface
    {
        self::assertNotNull($this->connection);

        return $this->connection;
    }

    private function time(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-21T00:00:00Z');
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}

final readonly class FixedPaymentIdentifierGenerator implements PaymentIdentifierGeneratorInterface
{
    public function __construct(private string $paymentId) {}

    public function generate(): string
    {
        return $this->paymentId;
    }
}

final class RecordingPaymentAudit implements PaymentAuditInterface
{
    /** @var list<string> */
    public array $actions = [];

    public function record(string $action, Payment $payment, DateTimeImmutable $occurredAt, string $correlationId): void
    {
        $this->actions[] = $action;
    }
}

final class FailingPaymentAudit implements PaymentAuditInterface
{
    public function record(string $action, Payment $payment, DateTimeImmutable $occurredAt, string $correlationId): void
    {
        throw new RuntimeException('Forced payment audit failure.');
    }
}

final class RecordingAuditEntryRecorder implements AuditEntryRecorderInterface
{
    public function record(AuditEntryData $auditEntry): AuditEntry
    {
        return AuditEntry::record(
            new AuditEntryId($auditEntry->auditEntryId),
            $auditEntry->occurredAt,
            AuditActorType::from($auditEntry->actor->type),
            $auditEntry->actor->identityId,
            $auditEntry->tenantId,
            $auditEntry->action,
            $auditEntry->target->type,
            $auditEntry->target->id,
            AuditOutcomeType::from($auditEntry->outcome->outcome),
            $auditEntry->outcome->reasonCode,
            $auditEntry->correlationId,
            $auditEntry->safeMetadata,
        );
    }
}

final class AfterCommitCommercialEventPublisher implements CommercialOfferEventPublisherInterface
{
    /** @var list<object> */
    public array $published = [];

    public function __construct(private ConnectionInterface $connection) {}

    public function publish(array $events): void
    {
        if ($this->connection->transactionLevel() === 0) {
            array_push($this->published, ...$events);

            return;
        }

        $this->connection->afterCommit(function () use ($events): void {
            array_push($this->published, ...$events);
        });
    }
}
