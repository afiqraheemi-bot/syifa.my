<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\WebsiteBuilder;

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
use App\Modules\Booking\Domain\ValueObjects\TenantId as BookingTenantId;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContextFactoryInterface;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 6 (Sprint 2): the same public Booking journey `PublicBookingJourneyTest`
 * exercises against local fakes, run here through the real container — real
 * Tenant resolver, real Availability reader, real Booking Form Configuration
 * reader, real Submission Gateway — against a real, freshly-seeded Postgres
 * database. Confirms Public Website -> Delivery Layer -> Booking Contracts ->
 * Booking Engine -> Booking Aggregate -> Booking History -> Success functions
 * end-to-end, with no test-local interface bindings anywhere in this file.
 *
 * Row-level isolation only, per the established pattern (see
 * BookingSubmissionGatewayAdapterIntegrationTest) — never touches table
 * structure, only inserts/deletes its own uniquely-generated rows, with a
 * `hasTable()` skip-guard for the shared disposable database.
 */
final class PublicBookingEndToEndIntegrationTest extends TestCase
{
    private const string CONNECTION = 'public_booking_end_to_end_integration';

    private const string HOST = 'e2e-clinic.example';

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
        foreach (['websites', 'clinic_operating_intervals', 'clinic_contact_profiles', 'services', 'booking_form_configurations', 'bookings', 'booking_slot_reservation_buckets', 'booking_history'] as $table) {
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
                $this->connection->table('websites')->where('tenant_id', $tenantId)->delete();
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

    public function test_the_full_public_journey_creates_a_real_booking_through_the_real_engine_without_ever_exposing_the_booking_id(): void
    {
        [$tenantId, $websiteId, $serviceId] = $this->seedTenantWebsiteClinicServiceAndFormConfiguration();
        $localDate = (new DateTimeImmutable('next monday'))->format('Y-m-d');

        $this->get('https://'.self::HOST.'/booking')
            ->assertRedirect('https://'.self::HOST.'/booking/service');

        $this->get('https://'.self::HOST.'/booking/service')
            ->assertOk()
            ->assertSee('Consultation')
            ->assertSee('Step 1 of 4');
        $this->post('https://'.self::HOST.'/booking/service', ['service_id' => $serviceId])
            ->assertRedirect('https://'.self::HOST.'/booking/date');

        $this->post('https://'.self::HOST.'/booking/date', [
            'appointment_date' => $localDate,
            'intent' => 'load_times',
        ])
            ->assertRedirect('https://'.self::HOST.'/booking/date');
        $this->post('https://'.self::HOST.'/booking/date', [
            'appointment_date' => $localDate,
            'appointment_time' => '09:00',
            'intent' => 'continue',
        ])
            ->assertRedirect('https://'.self::HOST.'/booking/details');

        $this->get('https://'.self::HOST.'/booking/details')->assertOk()->assertSee('Full name');
        $this->post('https://'.self::HOST.'/booking/details', [
            'patient_name' => 'Aisyah Rahman',
            'phone' => '+60123456789',
            'consent' => '1',
        ])->assertRedirect('https://'.self::HOST.'/booking/review');

        $review = $this->get('https://'.self::HOST.'/booking/review');
        $review->assertOk()->assertSee('Aisyah Rahman')->assertSee('Confirm Booking');
        $submissionToken = $this->extractHiddenValue($review->getContent(), 'submission_token');
        self::assertNotSame('', $submissionToken);

        $submit = $this->post('https://'.self::HOST.'/booking', ['submission_token' => $submissionToken]);
        $submit->assertRedirect();
        self::assertStringContainsString('/booking/success/', (string) $submit->headers->get('Location'));

        $success = $this->get((string) $submit->headers->get('Location'));
        $success->assertOk()->assertSee('received');
        self::assertStringNotContainsString($websiteId, $success->getContent());

        $booking = $this->connection->table('bookings')->where('tenant_id', $tenantId)->first();
        self::assertNotNull($booking);
        self::assertSame('Aisyah Rahman', $booking->patient_name);
        self::assertStringContainsString($booking->booking_reference, $success->getContent());
        self::assertStringNotContainsString($booking->id, $success->getContent());

        $bucket = $this->connection->table('booking_slot_reservation_buckets')->where('tenant_id', $tenantId)->first();
        self::assertNotNull($bucket);
        self::assertSame(1, $bucket->reserved_count);

        $history = $this->connection->table('booking_history')->where('tenant_id', $tenantId)->where('event_type', 'BookingSubmitted')->first();
        self::assertNotNull($history);
        $payload = json_decode((string) $history->payload, true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($payload['consent_acknowledged']);
    }

    /** @return array{0: string, 1: string, 2: string} tenantId, websiteId, serviceId */
    private function seedTenantWebsiteClinicServiceAndFormConfiguration(): array
    {
        $tenantId = (string) Str::uuid();
        $websiteId = (string) Str::uuid();
        $clinicId = (string) Str::uuid();
        $this->seededTenantIds[] = $tenantId;
        $now = now();

        $this->connection->table('tenants')->insert(['id' => $tenantId, 'status' => 'active', 'version' => 1, 'provisioned_at' => $now, 'created_at' => $now, 'updated_at' => $now]);

        $this->connection->table('websites')->insert([
            'id' => $websiteId, 'tenant_id' => $tenantId, 'template_id' => 'SYIFA_ESSENTIAL', 'lifecycle' => 'draft',
            'clinic_name' => 'Klinik End To End', 'primary_color' => '#112233', 'secondary_color' => '#AABBCC',
            'contact_email' => 'hello@e2e-clinic.test', 'contact_phone' => '+60123450000', 'address' => 'Kuala Lumpur',
            'domain_created_at' => $now, 'domain_updated_at' => $now, 'version' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);

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

        $tenant = new BookingTenantId($tenantId);
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

        config()->set('public_website_delivery.sites', [self::HOST => ['website_id' => $websiteId]]);
        $this->app->forgetInstance(PublicSiteContextFactoryInterface::class);

        return [$tenantId, $websiteId, $serviceId];
    }

    private function extractHiddenValue(string $html, string $name): string
    {
        if (preg_match('/name="'.preg_quote($name, '/').'" value="([^"]*)"/', $html, $matches) === 1) {
            return $matches[1];
        }

        return '';
    }
}
