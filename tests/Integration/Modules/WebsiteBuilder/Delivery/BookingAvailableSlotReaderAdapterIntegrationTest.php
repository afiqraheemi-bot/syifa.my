<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\WebsiteBuilder\Delivery;

use App\Modules\Booking\Contracts\Queries\AvailableSlotReaderInterface;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicAvailabilityState;
use App\Modules\WebsiteBuilder\Infrastructure\Delivery\BookingAvailableSlotReaderAdapter;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 1 (Sprint 2): confirms the real adapter, composed with the real
 * Booking Engine (ClinicOperationalTimeReaderInterface, ClinicSlotGenerator,
 * SlotCapacityReservationInterface — all resolved from the container exactly
 * as production does), reads real availability from real Postgres data.
 *
 * Deliberately touches no table *structure* — only inserts and, in tearDown,
 * deletes its own uniquely-generated rows. The Booking schema's migration
 * chain (bookings/booking_history/booking_slot_reservation_buckets/clinics)
 * is deep and interdependent; row-level isolation is simpler and more
 * robust here than replicating that chain, and never conflicts with any
 * other Integration test's own schema-level self-management.
 */
final class BookingAvailableSlotReaderAdapterIntegrationTest extends TestCase
{
    private const string CONNECTION = 'booking_available_slot_adapter_integration';

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
        if (! $schema->hasTable('clinic_operating_intervals') || ! $schema->hasTable('booking_slot_reservation_buckets') || ! $schema->hasTable('clinic_contact_profiles')) {
            self::markTestSkipped('Requires the full, freshly-migrated schema on the disposable database.');
        }
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            foreach ($this->seededTenantIds as $tenantId) {
                $this->connection->table('clinic_operating_intervals')->whereIn('clinic_id', function ($query) use ($tenantId): void {
                    $query->select('id')->from('clinics')->where('tenant_id', $tenantId);
                })->delete();
                $this->connection->table('clinic_contact_profiles')->where('tenant_id', $tenantId)->delete();
                $this->connection->table('clinics')->where('tenant_id', $tenantId)->delete();
                $this->connection->table('tenants')->where('id', $tenantId)->delete();
            }
            DB::purge(self::CONNECTION);
        }
        parent::tearDown();
    }

    public function test_real_engine_reports_available_slots_within_configured_operating_hours(): void
    {
        [$tenantId, $clinicId] = $this->seedClinic();
        $localDate = (new DateTimeImmutable('next monday'))->format('Y-m-d');
        $dayOfWeek = (int) (new DateTimeImmutable($localDate))->format('N');
        $this->connection->table('clinic_operating_intervals')->insert([
            'clinic_id' => $clinicId, 'day_of_week' => $dayOfWeek, 'opens_at' => '09:00:00', 'closes_at' => '10:00:00',
        ]);

        $reader = $this->app->make(AvailableSlotReaderInterface::class);
        $slots = (new BookingAvailableSlotReaderAdapter($reader))->forDate($tenantId, $localDate);

        self::assertNotEmpty($slots);
        foreach ($slots as $slot) {
            self::assertSame(PublicAvailabilityState::Available, $slot->state);
            self::assertSame($localDate, $slot->localDate);
        }
    }

    public function test_a_clinic_with_no_operating_hours_that_day_yields_an_empty_list(): void
    {
        [$tenantId] = $this->seedClinic();
        // No clinic_operating_intervals row inserted at all for this clinic.
        $localDate = (new DateTimeImmutable('next monday'))->format('Y-m-d');

        $reader = $this->app->make(AvailableSlotReaderInterface::class);
        $slots = (new BookingAvailableSlotReaderAdapter($reader))->forDate($tenantId, $localDate);

        self::assertSame([], $slots);
    }

    public function test_an_unknown_tenant_yields_an_empty_list_not_an_exception(): void
    {
        $reader = $this->app->make(AvailableSlotReaderInterface::class);

        $slots = (new BookingAvailableSlotReaderAdapter($reader))->forDate((string) Str::uuid(), '2026-08-03');

        self::assertSame([], $slots);
    }

    /** @return array{0: string, 1: string} tenantId, clinicId */
    private function seedClinic(): array
    {
        $tenantId = (string) Str::uuid();
        $clinicId = (string) Str::uuid();
        $this->seededTenantIds[] = $tenantId;
        $now = now();
        $this->connection->table('tenants')->insert(['id' => $tenantId, 'status' => 'active', 'version' => 1, 'provisioned_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        $this->connection->table('clinics')->insert([
            'id' => $clinicId, 'tenant_id' => $tenantId, 'timezone' => 'Asia/Kuala_Lumpur',
            'appointment_duration_minutes' => 30, 'booking_capacity_per_slot' => 2,
            'domain_created_at' => $now, 'domain_updated_at' => $now, 'version' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->connection->table('clinic_contact_profiles')->insert([
            'clinic_id' => $clinicId, 'tenant_id' => $tenantId,
            'operational_phone' => null, 'operational_email' => null, 'postal_address' => null,
            'whatsapp_number' => null, 'latitude' => null, 'longitude' => null,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return [$tenantId, $clinicId];
    }
}
