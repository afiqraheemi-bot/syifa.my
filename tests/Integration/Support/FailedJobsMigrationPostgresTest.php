<?php

declare(strict_types=1);

namespace Tests\Integration\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class FailedJobsMigrationPostgresTest extends TestCase
{
    private const CONNECTION = 'release_support_pgsql';

    protected function setUp(): void
    {
        parent::setUp();

        $dsn = getenv('RELEASE_POSTGRES_TEST_DSN') ?: getenv('SUBSCRIPTION_BILLING_POSTGRES_TEST_DSN');

        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Requires RELEASE_POSTGRES_TEST_DSN or SUBSCRIPTION_BILLING_POSTGRES_TEST_DSN.');
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
        ]);
        DB::purge(self::CONNECTION);
        Schema::connection(self::CONNECTION)->dropIfExists('failed_jobs');
    }

    protected function tearDown(): void
    {
        Schema::connection(self::CONNECTION)->dropIfExists('failed_jobs');
        DB::purge(self::CONNECTION);

        parent::tearDown();
    }

    public function test_failed_jobs_migration_applies_and_reverses_on_postgresql(): void
    {
        $migration = require database_path('migrations/2026_08_23_000001_create_failed_jobs_table.php');

        $migration->up();

        self::assertTrue(Schema::connection(self::CONNECTION)->hasColumns('failed_jobs', [
            'id',
            'uuid',
            'connection',
            'queue',
            'payload',
            'exception',
            'failed_at',
        ]));

        DB::connection(self::CONNECTION)->table('failed_jobs')->insert([
            'uuid' => '00000000-0000-4000-8000-000000000001',
            'connection' => 'redis',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'test',
        ]);

        self::assertSame(1, DB::connection(self::CONNECTION)->table('failed_jobs')->count());

        $migration->down();

        self::assertFalse(Schema::connection(self::CONNECTION)->hasTable('failed_jobs'));
    }
}
