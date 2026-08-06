<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\WebsiteBuilder\Delivery;

use App\Modules\Booking\Contracts\Queries\AvailableSlotReaderInterface;
use App\Modules\Booking\Contracts\Repositories\BookingFormConfigurationRepositoryInterface;
use App\Modules\Booking\Contracts\Repositories\ServiceRepositoryInterface;
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
use App\Modules\WebsiteBuilder\Contracts\Delivery\BookingSubmissionGatewayInterface;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicAvailabilityState;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingSubmission;
use App\Modules\WebsiteBuilder\Infrastructure\Delivery\BookingAvailableSlotReaderAdapter;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 3 (Sprint 2): confirms the real `BookingSubmissionGatewayInterface`
 * binding, resolved exactly as production does (every dependency wired
 * through the real container — no test-local fakes), reaches the real
 * Booking Engine and Postgres. Row-level isolation only, per the established
 * pattern (see BookingAvailableSlotReaderAdapterIntegrationTest) — the
 * Booking/WebsiteBuilder migration chains are deep and shared with every
 * other Integration test on this disposable database.
 */
final class BookingSubmissionGatewayAdapterIntegrationTest extends TestCase
{
    private const string CONNECTION = 'booking_submission_gateway_adapter_integration';

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
        foreach (['clinic_operating_intervals', 'clinic_contact_profiles', 'services', 'booking_form_configurations', 'bookings', 'booking_slot_reservation_buckets', 'booking_history'] as $table) {
            if (! $schema->hasTable($table)) {
                self::markTestSkipped('Requires the full, freshly-migrated schema on the disposable database.');
            }
        }
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            foreach ($this->seededTenantIds as $tenantId) {
                $this->connection->table('booking_history')->where('tenant_id', $tenantId)->delete();
                $this->connection->table('booking_slot_reservation_buckets')->where('tenant_id', $tenantId)->delete();
                $this->connection->table('bookings')->where('tenant_id', $tenantId)->delete();
                $this->connection->table('booking_form_configurations')->where('tenant_id', $tenantId)->delete();
                $this->connection->table('services')->where('tenant_id', $tenantId)->delete();
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

    public function test_a_public_submission_persists_a_real_booking_reserves_capacity_and_records_consent_without_ever_exposing_the_booking_id(): void
    {
        [$tenantId, $serviceId] = $this->seedTenantClinicServiceAndFormConfiguration();
        $localDate = (new DateTimeImmutable('next monday'))->format('Y-m-d');

        $submission = new PublicBookingSubmission($tenantId, 'Aisyah Rahman', '+60123456789', $localDate, '09:00', true, $serviceId);
        $gateway = $this->app->make(BookingSubmissionGatewayInterface::class);
        $result = $gateway->submit($submission);

        self::assertFalse(property_exists($result, 'bookingId'));
        self::assertSame('submitted', $result->status);

        $booking = $this->connection->table('bookings')->where('tenant_id', $tenantId)->first();
        self::assertNotNull($booking);
        self::assertSame($result->reference, $booking->booking_reference);
        self::assertSame('Aisyah Rahman', $booking->patient_name);

        $bucket = $this->connection->table('booking_slot_reservation_buckets')->where('tenant_id', $tenantId)->first();
        self::assertNotNull($bucket);
        self::assertSame(1, $bucket->reserved_count);

        $history = $this->connection->table('booking_history')->where('tenant_id', $tenantId)->where('event_type', 'BookingSubmitted')->first();
        self::assertNotNull($history);
        self::assertSame('public_visitor', $history->actor_type);
        $payload = json_decode((string) $history->payload, true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($payload['consent_acknowledged']);
    }

    public function test_a_slot_exhausted_by_real_submissions_is_immediately_reported_unavailable_by_the_real_availability_reader(): void
    {
        [$tenantId, $serviceId] = $this->seedTenantClinicServiceAndFormConfiguration();
        $localDate = (new DateTimeImmutable('next monday'))->format('Y-m-d');
        $gateway = $this->app->make(BookingSubmissionGatewayInterface::class);
        $reader = new BookingAvailableSlotReaderAdapter($this->app->make(AvailableSlotReaderInterface::class));

        $before = $this->slotAt($reader, $tenantId, $localDate, '09:00');
        self::assertSame(PublicAvailabilityState::Available, $before);

        // The seeded clinic's capacity is 2 per slot — two real submissions exhaust it.
        $gateway->submit(new PublicBookingSubmission($tenantId, 'Aisyah Rahman', '+60123456789', $localDate, '09:00', true, $serviceId));
        $gateway->submit(new PublicBookingSubmission($tenantId, 'Aiman Rahman', '+60123456780', $localDate, '09:00', true, $serviceId));

        $after = $this->slotAt($reader, $tenantId, $localDate, '09:00');
        self::assertSame(PublicAvailabilityState::Unavailable, $after);
    }

    private function slotAt(BookingAvailableSlotReaderAdapter $reader, string $tenantId, string $localDate, string $localStart): PublicAvailabilityState
    {
        foreach ($reader->forDate($tenantId, $localDate) as $slot) {
            if ($slot->localStart === $localStart) {
                return $slot->state;
            }
        }

        self::fail(sprintf('No slot starting at %s was reported.', $localStart));
    }

    /** @return array{0: string, 1: string} tenantId, serviceId */
    private function seedTenantClinicServiceAndFormConfiguration(): array
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
        $localDate = (new DateTimeImmutable('next monday'))->format('Y-m-d');
        $dayOfWeek = (int) (new DateTimeImmutable($localDate))->format('N');
        $this->connection->table('clinic_operating_intervals')->insert([
            'clinic_id' => $clinicId, 'day_of_week' => $dayOfWeek, 'opens_at' => '09:00:00', 'closes_at' => '12:00:00',
        ]);
        $this->connection->table('clinic_booking_availability_intervals')->insert([
            'clinic_id' => $clinicId, 'day_of_week' => $dayOfWeek, 'starts_at' => '09:00:00', 'ends_at' => '12:00:00',
        ]);

        $tenant = new TenantId($tenantId);
        $serviceId = (string) Str::uuid();
        $this->app->make(ServiceRepositoryInterface::class)->save(
            Service::register(new ServiceId($serviceId), $tenant, new ServiceName('Consultation'), null, new SortOrder(1), new DateTimeImmutable),
        );
        $this->app->make(BookingFormConfigurationRepositoryInterface::class)->save(
            BookingFormConfiguration::create(
                $tenant, true, false, false, false, false,
                new RequiredFields([BookingFormField::Service]),
                new FieldOrder([BookingFormField::PatientName, BookingFormField::Phone, BookingFormField::AppointmentDate, BookingFormField::AppointmentTime, BookingFormField::Service]),
                new FieldLabels([]),
                new DateTimeImmutable,
            ),
        );

        return [$tenantId, $serviceId];
    }
}
