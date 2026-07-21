<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\SubscriptionBilling\Subscription;

use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationApplicationResultCode;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationApplicationStatus;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\SubscriptionActivationApplicationPersistenceMapper;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresSubscriptionActivationApplicationRepository;
use DateTimeImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PostgresSubscriptionActivationApplicationRepositoryTest extends TestCase
{
    private const string CONNECTION = 'subscription_activation_application_postgres';

    private ?Migration $migration = null;

    private ?PostgresSubscriptionActivationApplicationRepository $repository = null;

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
        Schema::connection(self::CONNECTION)->dropIfExists('subscription_activation_reconciliation_cases');
        Schema::connection(self::CONNECTION)->dropIfExists('subscription_activation_applications');
        $migration = require base_path('database/migrations/subscription_billing/2026_07_28_000001_create_subscription_activation_tables.php');
        self::assertInstanceOf(Migration::class, $migration);
        $this->migration = $migration;
        $migration->up();
        $this->repository = new PostgresSubscriptionActivationApplicationRepository(DB::connection(self::CONNECTION), new SubscriptionActivationApplicationPersistenceMapper);
    }

    protected function tearDown(): void
    {
        $this->migration?->down();
        DB::purge(self::CONNECTION);
        parent::tearDown();
    }

    public function test_registration_claim_reclaim_and_stale_completion_lifecycle(): void
    {
        $now = new DateTimeImmutable('2026-07-25T00:00:00Z');
        $first = $this->repository()->register($this->uuid(1), $this->uuid(2), $this->uuid(3), $now);
        $duplicate = $this->repository()->register($this->uuid(1), $this->uuid(2), $this->uuid(3), $now);
        self::assertSame($first->id, $duplicate->id);
        self::assertSame($first->subscriptionId, $duplicate->subscriptionId);
        self::assertSame(1, DB::connection(self::CONNECTION)->table('subscription_activation_applications')->count());

        $claim = $this->repository()->claim($first->id, $now, 120);
        self::assertNotNull($claim);
        self::assertNotNull($claim->claimToken);
        self::assertSame(1, $claim->attemptCount);
        self::assertNull($this->repository()->claim($first->id, $now->modify('+1 minute'), 120));
        $reclaimed = $this->repository()->claim($first->id, $now->modify('+3 minutes'), 120);
        self::assertNotNull($reclaimed);
        self::assertNotSame($claim->claimToken, $reclaimed->claimToken);
        self::assertFalse($this->repository()->complete($first->id, (string) $claim->claimToken, SubscriptionActivationApplicationStatus::Applied, SubscriptionActivationApplicationResultCode::Applied, $now));
        self::assertTrue($this->repository()->complete($first->id, (string) $reclaimed->claimToken, SubscriptionActivationApplicationStatus::Applied, SubscriptionActivationApplicationResultCode::Applied, $now));
        self::assertSame(SubscriptionActivationApplicationStatus::Applied, $this->repository()->find($first->id)?->status);
    }

    private function repository(): PostgresSubscriptionActivationApplicationRepository
    {
        self::assertNotNull($this->repository);

        return $this->repository;
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
