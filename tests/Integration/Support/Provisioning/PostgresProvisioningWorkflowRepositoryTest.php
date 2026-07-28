<?php

declare(strict_types=1);

namespace Tests\Integration\Support\Provisioning;

use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivatedIntegrationEvent;
use App\Support\Provisioning\Infrastructure\PostgresProvisioningWorkflowRepository;
use Closure;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PostgresProvisioningWorkflowRepositoryTest extends TestCase
{
    private const string CONNECTION = 'provisioning_postgres_integration';

    private ?ConnectionInterface $connection = null;

    private ?Closure $down = null;

    protected function setUp(): void
    {
        parent::setUp();
        $dsn = getenv('SUBSCRIPTION_BILLING_POSTGRES_TEST_DSN');
        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Requires a dedicated disposable PostgreSQL database.');
        }
        config()->set('database.default', self::CONNECTION);
        config()->set('database.connections.'.self::CONNECTION, [
            'driver' => 'pgsql',
            'url' => $dsn,
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
            'timezone' => 'UTC',
        ]);
        DB::purge(self::CONNECTION);
        $this->connection = DB::connection(self::CONNECTION);
        Schema::connection(self::CONNECTION)->dropAllTables();
        foreach (['subscription_integration_outbox', 'subscriptions', 'clinic_registrations'] as $table) {
            Schema::connection(self::CONNECTION)->create($table, static function (Blueprint $blueprint): void {
                $blueprint->uuid('id')->primary();
            });
        }
        $migration = require base_path('database/migrations/provisioning/2026_09_01_000001_create_provisioning_workflows.php');
        $migration->up();
        $this->down = Closure::fromCallable([$migration, 'down']);
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            ($this->down)();
            foreach (['clinic_registrations', 'subscriptions', 'subscription_integration_outbox'] as $table) {
                Schema::connection(self::CONNECTION)->dropIfExists($table);
            }
            DB::purge(self::CONNECTION);
        }
        parent::tearDown();
    }

    public function test_workflow_claims_advance_retry_and_completion_are_lease_safe(): void
    {
        $event = new SubscriptionActivatedIntegrationEvent(
            $this->uuid(1),
            $this->uuid(2),
            $this->uuid(3),
            $this->uuid(4),
            $this->uuid(5),
            $this->uuid(6),
            $this->uuid(7),
            $this->uuid(8),
            '2026-09-01',
            '2027-08-31',
            new DateTimeImmutable('2026-09-01T00:00:00Z'),
        );
        $this->db()->table('subscription_integration_outbox')->insert(['id' => $event->eventId]);
        $this->db()->table('subscriptions')->insert(['id' => $event->subscriptionId]);
        $this->db()->table('clinic_registrations')->insert(['id' => $event->clinicRegistrationId]);
        $repository = new PostgresProvisioningWorkflowRepository($this->db());

        self::assertSame('tenant_provisioning', $repository->register($event)->currentStep);
        $claim = $repository->claimNext(new DateTimeImmutable('2026-09-01T00:01:00Z'));
        self::assertNotNull($claim);
        self::assertTrue($repository->advance(
            $claim->workflow->id,
            $claim->claimToken,
            'website_foundation',
            new DateTimeImmutable('2026-09-01T00:01:01Z'),
        ));
        self::assertFalse($repository->advance(
            $claim->workflow->id,
            $claim->claimToken,
            'booking_configuration',
            new DateTimeImmutable('2026-09-01T00:01:02Z'),
        ));

        $retryClaim = $repository->claimNext(new DateTimeImmutable('2026-09-01T00:01:03Z'));
        self::assertNotNull($retryClaim);
        self::assertTrue($repository->releaseForRetry(
            $retryClaim->workflow->id,
            $retryClaim->claimToken,
            new DateTimeImmutable('2026-09-01T00:01:30Z'),
            'safe_failure',
            new DateTimeImmutable('2026-09-01T00:01:04Z'),
        ));
        self::assertNull($repository->claimNext(new DateTimeImmutable('2026-09-01T00:01:29Z')));
        $finalClaim = $repository->claimNext(new DateTimeImmutable('2026-09-01T00:01:30Z'));
        self::assertNotNull($finalClaim);
        self::assertTrue($repository->complete(
            $finalClaim->workflow->id,
            $finalClaim->claimToken,
            new DateTimeImmutable('2026-09-01T00:01:31Z'),
        ));
        self::assertNull($repository->claimNext(new DateTimeImmutable('2026-09-01T00:02:00Z')));
    }

    private function db(): ConnectionInterface
    {
        return $this->connection ?? throw new \RuntimeException('PostgreSQL connection is unavailable.');
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
