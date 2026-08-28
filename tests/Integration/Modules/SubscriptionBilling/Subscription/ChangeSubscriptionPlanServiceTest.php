<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\SubscriptionBilling\Subscription;

use App\Modules\SubscriptionBilling\Application\Subscription\ChangeSubscriptionPlanService;
use App\Modules\SubscriptionBilling\Application\Subscription\SubscriptionTermCalculator;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\CapabilityDefinitionCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\BillingOptionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CapabilityDefinitionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CommercialCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\OffsetPaginationInput;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\OffsetPaginationMeta;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\PaginatedCapabilityDefinitionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PlanData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PlanOfferingData;
use App\Modules\SubscriptionBilling\Contracts\Subscription\ChangeSubscriptionPlanCommand;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regression coverage for a production incident: a tenant moved from the
 * 3-day trial to Syifa Pro (annual) via the Super Admin "change plan"
 * action, but the subscription's ends_on was silently left at the trial's
 * original 3-day expiry - a brand-new paid annual subscription expired
 * within days. ChangeSubscriptionPlanService never had any test coverage
 * before this.
 */
final class ChangeSubscriptionPlanServiceTest extends TestCase
{
    private const string CONNECTION = 'change_subscription_plan_postgres';

    public const string TRIAL_PLAN_ID = '00000000-0000-4000-8000-000000000001';

    public const string TRIAL_BILLING_OPTION_ID = '00000000-0000-4000-8000-000000000002';

    public const string TRIAL_OFFERING_ID = '00000000-0000-4000-8000-000000000003';

    public const string PRO_PLAN_ID = '00000000-0000-4000-8000-000000000004';

    public const string PRO_BILLING_OPTION_ID = '00000000-0000-4000-8000-000000000005';

    public const string PRO_OFFERING_ID = '00000000-0000-4000-8000-000000000006';

    public const string BASIC_PLAN_ID = '00000000-0000-4000-8000-000000000007';

    public const string BASIC_OFFERING_ID = '00000000-0000-4000-8000-000000000008';

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
        foreach (['2026_07_27_000001_create_subscriptions_table.php', '2026_07_30_000001_create_subscription_renewal_operations.php'] as $file) {
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

    public function test_switching_billing_cycle_from_trial_to_annual_starts_a_fresh_term(): void
    {
        $this->seedTrialSubscription();

        $result = $this->service()->change(new ChangeSubscriptionPlanCommand(
            $this->uuid(100),
            self::PRO_OFFERING_ID,
            $this->uuid(900),
            1,
            $this->uuid(901),
            new DateTimeImmutable('2026-08-23T14:00:00Z'),
        ));

        self::assertSame('plan_changed', $result);
        $row = $this->connection()->table('subscriptions')->where('id', $this->uuid(100))->first();
        self::assertNotNull($row);
        self::assertSame(self::PRO_PLAN_ID, $row->plan_id);
        self::assertSame(self::PRO_BILLING_OPTION_ID, $row->billing_cycle_id);
        self::assertSame('2026-08-23', (string) $row->starts_on);
        self::assertSame('2027-08-22', (string) $row->ends_on);
        self::assertSame(2, (int) $row->version);
        self::assertSame(
            'plan_changed',
            $this->connection()->table('subscription_timeline_entries')->where('subscription_id', $this->uuid(100))->value('event_type'),
        );
    }

    public function test_switching_plan_but_keeping_the_same_billing_cycle_preserves_the_existing_term(): void
    {
        $this->seedProSubscription();

        $result = $this->service()->change(new ChangeSubscriptionPlanCommand(
            $this->uuid(100),
            self::BASIC_OFFERING_ID,
            $this->uuid(900),
            1,
            $this->uuid(901),
            new DateTimeImmutable('2027-01-15T00:00:00Z'),
        ));

        self::assertSame('plan_changed', $result);
        $row = $this->connection()->table('subscriptions')->where('id', $this->uuid(100))->first();
        self::assertNotNull($row);
        self::assertSame(self::BASIC_PLAN_ID, $row->plan_id);
        self::assertSame(self::PRO_BILLING_OPTION_ID, $row->billing_cycle_id);
        self::assertSame('2026-08-23', (string) $row->starts_on);
        self::assertSame('2027-08-22', (string) $row->ends_on);
    }

    public function test_a_stale_expected_version_is_rejected_without_mutating_the_subscription(): void
    {
        $this->seedTrialSubscription();

        $result = $this->service()->change(new ChangeSubscriptionPlanCommand(
            $this->uuid(100),
            self::PRO_OFFERING_ID,
            $this->uuid(900),
            99,
            $this->uuid(901),
            new DateTimeImmutable('2026-08-23T14:00:00Z'),
        ));

        self::assertSame('version_conflict', $result);
        $row = $this->connection()->table('subscriptions')->where('id', $this->uuid(100))->first();
        self::assertNotNull($row);
        self::assertSame(self::TRIAL_PLAN_ID, $row->plan_id);
        self::assertSame('2026-08-25', (string) $row->ends_on);
        self::assertSame(1, (int) $row->version);
    }

    private function service(): ChangeSubscriptionPlanService
    {
        return new ChangeSubscriptionPlanService(
            $this->connection(),
            $this->catalogue(),
            $this->capabilities(),
            new SubscriptionTermCalculator,
        );
    }

    private function catalogue(): CommercialCatalogueQueryInterface
    {
        return new class implements CommercialCatalogueQueryInterface
        {
            public function findPlan(string $planId): ?PlanData
            {
                return match ($planId) {
                    ChangeSubscriptionPlanServiceTest::TRIAL_PLAN_ID => new PlanData($planId, 'syifa-trial', 'Syifa Trial', '', 'active', 10, '2026-01-01T00:00:00Z', '2026-01-01T00:00:00Z'),
                    ChangeSubscriptionPlanServiceTest::PRO_PLAN_ID => new PlanData($planId, 'syifa-pro', 'Syifa Pro', '', 'active', 30, '2026-01-01T00:00:00Z', '2026-01-01T00:00:00Z'),
                    ChangeSubscriptionPlanServiceTest::BASIC_PLAN_ID => new PlanData($planId, 'syifa-basic', 'Syifa Basic', '', 'active', 20, '2026-01-01T00:00:00Z', '2026-01-01T00:00:00Z'),
                    default => null,
                };
            }

            public function findBillingOption(string $billingOptionId): ?BillingOptionData
            {
                return match ($billingOptionId) {
                    ChangeSubscriptionPlanServiceTest::TRIAL_BILLING_OPTION_ID => new BillingOptionData($billingOptionId, 'syifa-trial-3-day', '3-Day Trial', 'available', 'recurring', 'day', 3, '2026-01-01', null, 10),
                    ChangeSubscriptionPlanServiceTest::PRO_BILLING_OPTION_ID => new BillingOptionData($billingOptionId, 'syifa-annual', 'Annual Billing', 'available', 'recurring', 'year', 1, '2026-01-01', null, 20),
                    default => null,
                };
            }

            public function findPlanOffering(string $planOfferingId): ?PlanOfferingData
            {
                return match ($planOfferingId) {
                    ChangeSubscriptionPlanServiceTest::TRIAL_OFFERING_ID => new PlanOfferingData($planOfferingId, ChangeSubscriptionPlanServiceTest::TRIAL_PLAN_ID, ChangeSubscriptionPlanServiceTest::TRIAL_BILLING_OPTION_ID, 0, 'MYR', 'active', '2026-01-01', null, 'v1', 'package:syifa-trial', 10),
                    ChangeSubscriptionPlanServiceTest::PRO_OFFERING_ID => new PlanOfferingData($planOfferingId, ChangeSubscriptionPlanServiceTest::PRO_PLAN_ID, ChangeSubscriptionPlanServiceTest::PRO_BILLING_OPTION_ID, 39900, 'MYR', 'active', '2026-01-01', null, 'v1', 'package:syifa-pro', 30),
                    ChangeSubscriptionPlanServiceTest::BASIC_OFFERING_ID => new PlanOfferingData($planOfferingId, ChangeSubscriptionPlanServiceTest::BASIC_PLAN_ID, ChangeSubscriptionPlanServiceTest::PRO_BILLING_OPTION_ID, 29900, 'MYR', 'active', '2026-01-01', null, 'v1', 'package:syifa-basic', 20),
                    default => null,
                };
            }

            public function findCapability(string $capabilityId): ?CapabilityDefinitionData
            {
                return null;
            }
        };
    }

    private function capabilities(): CapabilityDefinitionCatalogueQueryInterface
    {
        return new class implements CapabilityDefinitionCatalogueQueryInterface
        {
            public function listCapabilityDefinitions(OffsetPaginationInput $pagination): PaginatedCapabilityDefinitionData
            {
                $items = [
                    new CapabilityDefinitionData($this->id(1), 'website.managed', 'Managed Website', '', '', 'active'),
                    new CapabilityDefinitionData($this->id(2), 'syifa_ai.assist', 'SYIFA AI', '', '', 'active'),
                ];

                return new PaginatedCapabilityDefinitionData($items, new OffsetPaginationMeta(1, 100, count($items), 1, 1, count($items)));
            }

            private function id(int $suffix): string
            {
                return sprintf('00000000-0000-4000-8000-%012d', 800 + $suffix);
            }
        };
    }

    private function seedTrialSubscription(): void
    {
        $this->connection()->table('subscriptions')->insert([
            'id' => $this->uuid(100),
            'tenant_id' => $this->uuid(200),
            'clinic_registration_id' => $this->uuid(300),
            'payment_id' => $this->uuid(400),
            'commercial_offer_id' => $this->uuid(500),
            'plan_id' => self::TRIAL_PLAN_ID,
            'billing_cycle_id' => self::TRIAL_BILLING_OPTION_ID,
            'amount_minor' => 0,
            'currency' => 'MYR',
            'starts_on' => '2026-08-22',
            'ends_on' => '2026-08-25',
            'status' => 'active',
            'entitlement_configuration_version' => 'package:syifa-trial',
            'entitlement_status' => 'effective',
            'entitlement_capabilities' => json_encode(['website.managed']),
            'created_at_domain' => '2026-08-22 00:00:00',
            'last_changed_at' => '2026-08-22 00:00:00',
            'version' => 1,
            'created_at' => '2026-08-22 00:00:00',
            'updated_at' => '2026-08-22 00:00:00',
        ]);
    }

    private function seedProSubscription(): void
    {
        $this->connection()->table('subscriptions')->insert([
            'id' => $this->uuid(100),
            'tenant_id' => $this->uuid(200),
            'clinic_registration_id' => $this->uuid(300),
            'payment_id' => $this->uuid(400),
            'commercial_offer_id' => $this->uuid(500),
            'plan_id' => self::PRO_PLAN_ID,
            'billing_cycle_id' => self::PRO_BILLING_OPTION_ID,
            'amount_minor' => 39900,
            'currency' => 'MYR',
            'starts_on' => '2026-08-23',
            'ends_on' => '2027-08-22',
            'status' => 'active',
            'entitlement_configuration_version' => 'package:syifa-pro',
            'entitlement_status' => 'effective',
            'entitlement_capabilities' => json_encode(['website.managed', 'syifa_ai.assist']),
            'created_at_domain' => '2026-08-23 00:00:00',
            'last_changed_at' => '2026-08-23 00:00:00',
            'version' => 1,
            'created_at' => '2026-08-23 00:00:00',
            'updated_at' => '2026-08-23 00:00:00',
        ]);
    }

    private function dropTables(): void
    {
        foreach (['subscription_timeline_entries', 'subscription_renewals', 'subscriptions'] as $table) {
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
