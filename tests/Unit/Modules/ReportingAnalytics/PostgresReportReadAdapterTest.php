<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\ReportingAnalytics;

use App\Modules\ReportingAnalytics\Application\MetricDefinitionCatalogue;
use App\Modules\ReportingAnalytics\Infrastructure\PostgresReportReadAdapter;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PostgresReportReadAdapterTest extends TestCase
{
    private const string CONNECTION = 'report_projection_test';

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
        $schema->create('tenants', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('status');
        });
        $schema->create('clinic_registrations', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('status');
        });
        $schema->create('websites', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('lifecycle');
        });
        $schema->create('subscriptions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('status');
            $table->string('starts_on');
            $table->string('ends_on');
        });
        $schema->create('services', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('name');
        });
        $schema->create('bookings', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('service_id')->nullable();
            $table->string('status');
        });
        $schema->create('onboarding_jobs', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('status');
        });
        $schema->create('website_designer_assignments', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('onboarding_job_id');
            $table->string('platform_identity_id');
            $table->string('assignment_status');
        });
    }

    protected function tearDown(): void
    {
        DB::purge(self::CONNECTION);
        parent::tearDown();
    }

    public function test_clinic_report_is_tenant_scoped_and_includes_service_activity(): void
    {
        $this->connection->table('websites')->insert([
            ['id' => 'website-a', 'tenant_id' => 'tenant-a', 'lifecycle' => 'published'],
            ['id' => 'website-b', 'tenant_id' => 'tenant-b', 'lifecycle' => 'draft'],
        ]);
        $this->connection->table('subscriptions')->insert([
            'id' => 'subscription-a',
            'tenant_id' => 'tenant-a',
            'status' => 'active',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);
        $this->connection->table('services')->insert([
            ['id' => 'service-a', 'tenant_id' => 'tenant-a', 'name' => 'Consultation'],
            ['id' => 'service-b', 'tenant_id' => 'tenant-b', 'name' => 'Foreign Service'],
        ]);
        $this->connection->table('bookings')->insert([
            ['id' => 'booking-a1', 'tenant_id' => 'tenant-a', 'service_id' => 'service-a', 'status' => 'submitted'],
            ['id' => 'booking-a2', 'tenant_id' => 'tenant-a', 'service_id' => 'service-a', 'status' => 'confirmed'],
            ['id' => 'booking-a3', 'tenant_id' => 'tenant-a', 'service_id' => null, 'status' => 'submitted'],
            ['id' => 'booking-b', 'tenant_id' => 'tenant-b', 'service_id' => 'service-b', 'status' => 'submitted'],
        ]);

        $report = $this->adapter()->clinic('tenant-a');

        self::assertSame(3, $report['metrics']['bookingTotal']);
        self::assertSame(['confirmed' => 1, 'submitted' => 2], $report['metrics']['bookingsByStatus']);
        self::assertSame(
            ['Not selected' => 1, 'Consultation' => 2],
            $report['metrics']['bookingsByService'],
        );
        self::assertArrayNotHasKey('Foreign Service', $report['metrics']['bookingsByService']);
    }

    public function test_portfolio_report_exposes_governed_module_status_and_booking_adoption(): void
    {
        $this->connection->table('tenants')->insert([
            ['id' => 'tenant-a', 'status' => 'active'],
            ['id' => 'tenant-b', 'status' => 'suspended'],
        ]);
        $this->connection->table('clinic_registrations')->insert([
            ['id' => 'registration-a', 'status' => 'submitted'],
            ['id' => 'registration-b', 'status' => 'approved'],
        ]);
        $this->connection->table('websites')->insert([
            ['id' => 'website-a', 'tenant_id' => 'tenant-a', 'lifecycle' => 'published'],
            ['id' => 'website-b', 'tenant_id' => 'tenant-b', 'lifecycle' => 'draft'],
        ]);
        $this->connection->table('subscriptions')->insert([
            ['id' => 'subscription-a', 'tenant_id' => 'tenant-a', 'status' => 'active', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31'],
            ['id' => 'subscription-b', 'tenant_id' => 'tenant-b', 'status' => 'expired', 'starts_on' => '2025-01-01', 'ends_on' => '2025-12-31'],
        ]);
        $this->connection->table('bookings')->insert([
            ['id' => 'booking-a1', 'tenant_id' => 'tenant-a', 'service_id' => null, 'status' => 'submitted'],
            ['id' => 'booking-a2', 'tenant_id' => 'tenant-a', 'service_id' => null, 'status' => 'confirmed'],
            ['id' => 'booking-b', 'tenant_id' => 'tenant-b', 'service_id' => null, 'status' => 'cancelled'],
        ]);
        $this->connection->table('onboarding_jobs')->insert([
            ['id' => 'job-a', 'status' => 'in_progress'],
            ['id' => 'job-b', 'status' => 'completed'],
        ]);

        $metrics = $this->adapter()->portfolio()['metrics'];

        self::assertSame(2, $metrics['bookingAdoptingTenants']);
        self::assertSame(['approved' => 1, 'submitted' => 1], $metrics['registrationsByStatus']);
        self::assertSame(['draft' => 1, 'published' => 1], $metrics['websitesByStatus']);
        self::assertSame(['active' => 1, 'expired' => 1], $metrics['subscriptionsByStatus']);
        self::assertSame(['cancelled' => 1, 'confirmed' => 1, 'submitted' => 1], $metrics['bookingsByStatus']);
        self::assertSame(['completed' => 1, 'in_progress' => 1], $metrics['onboardingByStatus']);
    }

    private function adapter(): PostgresReportReadAdapter
    {
        return new PostgresReportReadAdapter(
            $this->connection,
            new MetricDefinitionCatalogue,
        );
    }
}
