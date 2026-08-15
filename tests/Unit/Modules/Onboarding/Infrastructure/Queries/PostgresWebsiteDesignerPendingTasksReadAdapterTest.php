<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Onboarding\Infrastructure\Queries;

use App\Modules\Onboarding\Infrastructure\Queries\PostgresWebsiteDesignerDashboardReadAdapter;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PostgresWebsiteDesignerPendingTasksReadAdapterTest extends TestCase
{
    private const string CONNECTION = 'designer_pending_tasks_test';

    private ConnectionInterface $connection;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.'.self::CONNECTION, [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge(self::CONNECTION);
        $this->connection = DB::connection(self::CONNECTION);
        $schema = $this->connection->getSchemaBuilder();

        $schema->create('onboarding_jobs', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('website_id');
            $table->string('status');
        });
        $schema->create('website_designer_assignments', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('onboarding_job_id');
            $table->string('tenant_id');
            $table->string('platform_identity_id');
            $table->string('assignment_status');
        });
        $schema->create('onboarding_tasks', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('onboarding_job_id');
            $table->string('tenant_id');
            $table->string('responsibility');
            $table->string('status');
            $table->timestamp('task_updated_at');
        });
        $schema->create('websites', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('clinic_name');
        });
    }

    protected function tearDown(): void
    {
        DB::purge(self::CONNECTION);
        parent::tearDown();
    }

    public function test_it_only_alerts_the_current_designer_about_unfinished_assigned_tasks(): void
    {
        $this->connection->table('onboarding_jobs')->insert([
            ['id' => 'job-a', 'tenant_id' => 'tenant-a', 'website_id' => 'website-a', 'status' => 'in_progress'],
            ['id' => 'job-b', 'tenant_id' => 'tenant-b', 'website_id' => 'website-b', 'status' => 'in_progress'],
        ]);
        $this->connection->table('websites')->insert([
            ['id' => 'website-a', 'tenant_id' => 'tenant-a', 'clinic_name' => 'Klinik A'],
            ['id' => 'website-b', 'tenant_id' => 'tenant-b', 'clinic_name' => 'Klinik B'],
        ]);
        $this->connection->table('website_designer_assignments')->insert([
            ['id' => 'assignment-a', 'onboarding_job_id' => 'job-a', 'tenant_id' => 'tenant-a', 'platform_identity_id' => 'designer-a', 'assignment_status' => 'active'],
            ['id' => 'assignment-b', 'onboarding_job_id' => 'job-b', 'tenant_id' => 'tenant-b', 'platform_identity_id' => 'designer-b', 'assignment_status' => 'active'],
        ]);
        $this->connection->table('onboarding_tasks')->insert([
            ['id' => 'task-a1', 'onboarding_job_id' => 'job-a', 'tenant_id' => 'tenant-a', 'responsibility' => 'website_designer', 'status' => 'ready', 'task_updated_at' => '2026-08-15 10:00:00'],
            ['id' => 'task-a2', 'onboarding_job_id' => 'job-a', 'tenant_id' => 'tenant-a', 'responsibility' => 'website_designer', 'status' => 'completed', 'task_updated_at' => '2026-08-15 09:00:00'],
            ['id' => 'task-owner', 'onboarding_job_id' => 'job-a', 'tenant_id' => 'tenant-a', 'responsibility' => 'clinic_owner', 'status' => 'ready', 'task_updated_at' => '2026-08-15 11:00:00'],
            ['id' => 'task-b1', 'onboarding_job_id' => 'job-b', 'tenant_id' => 'tenant-b', 'responsibility' => 'website_designer', 'status' => 'blocked', 'task_updated_at' => '2026-08-15 12:00:00'],
        ]);

        $adapter = new PostgresWebsiteDesignerDashboardReadAdapter($this->connection);

        self::assertSame(1, $adapter->countPendingFor('designer-a'));
        self::assertSame([
            [
                'id' => 'job-a',
                'clinic_name' => 'Klinik A',
                'status' => 'in_progress',
                'pending_tasks' => 1,
            ],
        ], $adapter->recentPendingFor('designer-a', 5));
    }
}
