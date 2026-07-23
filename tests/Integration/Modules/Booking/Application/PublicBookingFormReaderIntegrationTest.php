<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Booking\Application;

use App\Modules\Booking\Contracts\Queries\PublicBookingFormReaderInterface;
use App\Modules\Booking\Domain\BookingFormConfiguration;
use App\Modules\Booking\Domain\Service;
use App\Modules\Booking\Domain\ValueObjects\BookingFormField;
use App\Modules\Booking\Domain\ValueObjects\FieldLabels;
use App\Modules\Booking\Domain\ValueObjects\FieldOrder;
use App\Modules\Booking\Domain\ValueObjects\RequiredFields;
use App\Modules\Booking\Domain\ValueObjects\ServiceId;
use App\Modules\Booking\Domain\ValueObjects\ServiceName;
use App\Modules\Booking\Domain\ValueObjects\SortOrder;
use App\Modules\Booking\Domain\ValueObjects\TenantId;
use App\Modules\Booking\Infrastructure\Persistence\Mappers\BookingFormConfigurationPersistenceMapper;
use App\Modules\Booking\Infrastructure\Persistence\Mappers\ServicePersistenceMapper;
use App\Modules\Booking\Infrastructure\Persistence\Repositories\PostgresBookingFormConfigurationRepository;
use App\Modules\Booking\Infrastructure\Persistence\Repositories\PostgresServiceRepository;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 2 (Sprint 2): confirms the real Contracts-layer query
 * (`PublicBookingFormReaderInterface`, resolved from the container exactly
 * as production does) reads real `BookingFormConfiguration` and `Service`
 * data from real Postgres, and never exposes Doctor/Branch.
 *
 * Row-level isolation only (no schema mutation) — seeds via the real
 * repositories' own `save()`, deletes only its own tenant's rows in
 * tearDown, matching Phase 1's resolution of shared-schema fragility.
 */
final class PublicBookingFormReaderIntegrationTest extends TestCase
{
    private const string CONNECTION = 'public_booking_form_reader_integration';

    private ?ConnectionInterface $connection = null;

    /** @var list<string> */
    private array $seededTenantIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $dsn = getenv('PUBLIC_BOOKING_POSTGRES_TEST_DSN') ?: getenv('BOOKING_POSTGRES_TEST_DSN') ?: getenv('WEBSITE_POSTGRES_TEST_DSN') ?: getenv('CLINIC_POSTGRES_TEST_DSN');
        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Requires a dedicated disposable PostgreSQL database.');
        }
        config()->set('database.default', self::CONNECTION);
        config()->set('database.connections.'.self::CONNECTION, ['driver' => 'pgsql', 'url' => $dsn, 'charset' => 'utf8', 'prefix' => '', 'prefix_indexes' => true, 'search_path' => 'public', 'sslmode' => 'prefer', 'timezone' => 'UTC']);
        DB::purge(self::CONNECTION);
        $this->connection = DB::connection(self::CONNECTION);

        $schema = $this->connection->getSchemaBuilder();
        if (! $schema->hasTable('booking_form_configurations') || ! $schema->hasTable('services')) {
            self::markTestSkipped('Requires the full, freshly-migrated schema on the disposable database.');
        }
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            foreach ($this->seededTenantIds as $tenantId) {
                $this->connection->table('services')->where('tenant_id', $tenantId)->delete();
                $this->connection->table('booking_form_configurations')->where('tenant_id', $tenantId)->delete();
            }
            DB::purge(self::CONNECTION);
        }
        parent::tearDown();
    }

    public function test_it_reads_real_configuration_and_active_services_and_never_doctor_or_branch(): void
    {
        $tenantId = $this->seedTenant();
        $this->saveConfiguration($tenantId, serviceSelection: true, email: true, notes: false);
        $this->saveService($tenantId, 'General Consultation', 0);
        $this->saveService($tenantId, 'Health Screening', 1);

        $reader = $this->app->make(PublicBookingFormReaderInterface::class);
        $data = $reader->forTrustedTenant($tenantId->value);

        self::assertTrue($data->serviceSelectionEnabled);
        self::assertTrue($data->emailEnabled);
        self::assertFalse($data->notesEnabled);
        self::assertCount(2, $data->services);
        self::assertSame('General Consultation', $data->services[0]->name);
        self::assertSame('Health Screening', $data->services[1]->name);
        self::assertFalse(property_exists($data, 'doctorSelectionEnabled'));
        self::assertFalse(property_exists($data, 'branchEnabled'));
    }

    public function test_inactive_services_are_never_returned(): void
    {
        $tenantId = $this->seedTenant();
        $this->saveConfiguration($tenantId, serviceSelection: true, email: false, notes: false);
        $service = $this->saveService($tenantId, 'Retired Service', 0);
        $service->deactivate(new DateTimeImmutable);
        (new PostgresServiceRepository($this->connection, new ServicePersistenceMapper))->save($service);

        $reader = $this->app->make(PublicBookingFormReaderInterface::class);
        $data = $reader->forTrustedTenant($tenantId->value);

        self::assertSame([], $data->services);
    }

    public function test_a_tenant_with_no_configuration_yet_fails_closed(): void
    {
        $tenantId = $this->seedTenant();
        // No BookingFormConfiguration saved at all for this tenant.

        $reader = $this->app->make(PublicBookingFormReaderInterface::class);
        $data = $reader->forTrustedTenant($tenantId->value);

        self::assertFalse($data->serviceSelectionEnabled);
        self::assertSame([], $data->services);
    }

    private function seedTenant(): TenantId
    {
        $tenantId = new TenantId((string) Str::uuid());
        $this->seededTenantIds[] = $tenantId->value;

        return $tenantId;
    }

    private function saveConfiguration(TenantId $tenantId, bool $serviceSelection, bool $email, bool $notes): void
    {
        $order = [BookingFormField::PatientName, BookingFormField::Phone, BookingFormField::AppointmentDate, BookingFormField::AppointmentTime];
        if ($serviceSelection) {
            $order[] = BookingFormField::Service;
        }
        if ($email) {
            $order[] = BookingFormField::Email;
        }
        if ($notes) {
            $order[] = BookingFormField::Notes;
        }

        $configuration = BookingFormConfiguration::create(
            $tenantId,
            enableServiceSelection: $serviceSelection,
            enableDoctorSelection: false,
            enableEmail: $email,
            enableBranch: false,
            enableNotes: $notes,
            requiredFields: new RequiredFields([]),
            fieldOrder: new FieldOrder($order),
            fieldLabels: new FieldLabels([]),
            occurredAt: new DateTimeImmutable,
        );

        (new PostgresBookingFormConfigurationRepository($this->connection, new BookingFormConfigurationPersistenceMapper))->save($configuration);
    }

    private function saveService(TenantId $tenantId, string $name, int $sortOrder): Service
    {
        $service = Service::register(new ServiceId((string) Str::uuid()), $tenantId, new ServiceName($name), null, new SortOrder($sortOrder), new DateTimeImmutable);
        (new PostgresServiceRepository($this->connection, new ServicePersistenceMapper))->save($service);

        return $service;
    }
}
