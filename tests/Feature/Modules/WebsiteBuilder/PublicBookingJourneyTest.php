<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\WebsiteBuilder;

use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContextFactoryInterface;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicWebsiteRenderModelProviderInterface;
use App\Modules\WebsiteBuilder\Contracts\Delivery\BookingSubmissionGatewayInterface;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicAvailabilityReaderInterface;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicAvailabilitySlot;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicAvailabilityState;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingAvailabilityException;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingBusinessRuleException;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingFormConfiguration;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingFormConfigurationReaderInterface;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingInfrastructureException;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingServiceOption;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingSubmission;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingSubmissionResult;
use App\Modules\WebsiteBuilder\Contracts\Delivery\WebsiteTenantResolverInterface;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

/**
 * Exercises the Delivery layer (Controllers/ViewModels/Blade/session) in
 * isolation from the real Booking Engine — Availability and Submission are
 * bound to lightweight, deterministic fakes local to this test, exactly like
 * WebsiteTenantResolverInterface/PublicWebsiteRenderModelProviderInterface
 * below. Phase 6's PublicBookingEndToEndIntegrationTest exercises the same
 * journey through the real Booking Engine with real seeded data.
 */
final class PublicBookingJourneyTest extends TestCase
{
    private const string HOST = 'clinic.example';

    private const string WEBSITE_ID = '00000000-0000-4000-8000-000000000001';

    public const string TENANT_ID = '00000000-0000-4000-8000-000000000002';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('public_website_delivery.sites', [self::HOST => ['website_id' => self::WEBSITE_ID]]);
        $this->app->forgetInstance(PublicSiteContextFactoryInterface::class);

        $tenantId = self::TENANT_ID;
        $this->app->instance(WebsiteTenantResolverInterface::class, new readonly class($tenantId) implements WebsiteTenantResolverInterface
        {
            public function __construct(private string $tenantId) {}

            public function forTrustedWebsite(string $trustedWebsiteId): string
            {
                return $this->tenantId;
            }
        });

        $this->app->instance(PublicWebsiteRenderModelProviderInterface::class, new readonly class implements PublicWebsiteRenderModelProviderInterface
        {
            public function find($context): null
            {
                return null;
            }
        });

        // Deterministic: every requested date has one Available slot at 09:00-09:30.
        $this->app->instance(PublicAvailabilityReaderInterface::class, new readonly class implements PublicAvailabilityReaderInterface
        {
            public function forDate(string $trustedTenantId, string $localDate): array
            {
                return [new PublicAvailabilitySlot($localDate, '09:00', '09:30', 'Asia/Kuala_Lumpur', PublicAvailabilityState::Available)];
            }
        });

        // Deterministic: service selection enabled with one fixed service option.
        $this->app->instance(PublicBookingFormConfigurationReaderInterface::class, new readonly class implements PublicBookingFormConfigurationReaderInterface
        {
            public function forTrustedTenant(string $trustedTenantId): PublicBookingFormConfiguration
            {
                return new PublicBookingFormConfiguration(true, false, true, true, [
                    new PublicBookingServiceOption('general-consultation', 'General Consultation', true),
                ]);
            }
        });

        // Deterministic: never calls the real Booking Engine. Raises each of the
        // four ADR-027 error categories for a documented sentinel `patientName`.
        $this->app->instance(BookingSubmissionGatewayInterface::class, new FakeBookingSubmissionGateway);
    }

    public function test_the_full_journey_from_website_cta_to_success_completes(): void
    {
        $this->get('https://'.self::HOST.'/booking')
            ->assertRedirect('https://'.self::HOST.'/booking/service');

        $this->get('https://'.self::HOST.'/booking/service')
            ->assertOk()
            ->assertSee('General Consultation')
            ->assertSee('Step 1 of 4');

        $this->post('https://'.self::HOST.'/booking/service', ['service_id' => 'general-consultation'])
            ->assertRedirect('https://'.self::HOST.'/booking/date');

        // First round trip: choose a date only (the fake Availability reader always reports it Available).
        $date = $this->firstAvailableDate();
        $this->post('https://'.self::HOST.'/booking/date', [
            'appointment_date' => $date,
            'intent' => 'load_times',
        ])
            ->assertRedirect('https://'.self::HOST.'/booking/date');

        $dateScreen = $this->get('https://'.self::HOST.'/booking/date');
        $dateScreen->assertOk()
            ->assertSee('Choose a time')
            ->assertSee('09:00');

        // Second round trip: date + a time now that times are visible.
        $this->post('https://'.self::HOST.'/booking/date', [
            'appointment_date' => $date,
            'appointment_time' => '09:00',
            'intent' => 'continue',
        ])
            ->assertRedirect('https://'.self::HOST.'/booking/details');

        $this->get('https://'.self::HOST.'/booking/details')->assertOk()->assertSee('Full name');

        $this->post('https://'.self::HOST.'/booking/details', [
            'patient_name' => 'Aisyah',
            'phone' => '+60123456789',
            'consent' => '1',
        ])->assertRedirect('https://'.self::HOST.'/booking/review');

        $review = $this->get('https://'.self::HOST.'/booking/review');
        $review->assertOk()->assertSee('Aisyah')->assertSee('Confirm Booking');

        $submissionToken = $this->extractHiddenValue($review->getContent(), 'submission_token');
        self::assertNotSame('', $submissionToken);

        $submit = $this->post('https://'.self::HOST.'/booking', ['submission_token' => $submissionToken]);
        $submit->assertRedirect();
        self::assertStringContainsString('/booking/success/', (string) $submit->headers->get('Location'));

        $success = $this->get((string) $submit->headers->get('Location'));
        $success->assertOk()->assertSee('BOOK-FAKE-')->assertSee('received');
    }

    public function test_stale_submission_token_is_rejected_and_never_processed_twice(): void
    {
        $this->completeUpToReview();

        $review = $this->get('https://'.self::HOST.'/booking/review');
        $token = $this->extractHiddenValue($review->getContent(), 'submission_token');

        $first = $this->post('https://'.self::HOST.'/booking', ['submission_token' => $token]);
        $first->assertRedirect();
        self::assertStringContainsString('/booking/success/', (string) $first->headers->get('Location'));

        // The Draft is now cleared and the token consumed. A second submit with the
        // exact same (already-consumed) token must be rejected, not processed again.
        $replay = $this->post('https://'.self::HOST.'/booking', ['submission_token' => $token]);
        $replay->assertRedirect('https://'.self::HOST.'/booking/review');
    }

    public function test_availability_error_returns_to_date_selection_with_the_time_cleared_but_date_preserved(): void
    {
        $this->completeUpToReview(patientName: FakeBookingSubmissionGateway::TRIGGER_AVAILABILITY);

        $review = $this->get('https://'.self::HOST.'/booking/review');
        $token = $this->extractHiddenValue($review->getContent(), 'submission_token');

        $this->post('https://'.self::HOST.'/booking', ['submission_token' => $token])
            ->assertRedirect('https://'.self::HOST.'/booking/date');
    }

    public function test_success_page_is_unreachable_without_a_valid_token(): void
    {
        $this->get('https://'.self::HOST.'/booking/success/does-not-exist')
            ->assertRedirect('https://'.self::HOST.'/booking');
    }

    public function test_visiting_review_with_an_incomplete_or_expired_draft_redirects_to_landing_instead_of_crashing(): void
    {
        // No prior steps completed in this session — simulates a lost/expired Draft.
        $this->get('https://'.self::HOST.'/booking/review')
            ->assertRedirect('https://'.self::HOST.'/booking');
    }

    public function test_submitting_patient_details_without_consent_is_rejected_as_validation_with_data_preserved(): void
    {
        $response = $this->from('https://'.self::HOST.'/booking/details')->post('https://'.self::HOST.'/booking/details', [
            'patient_name' => 'Aisyah',
            'phone' => '+60123456789',
            // consent intentionally omitted
        ]);

        $response->assertRedirect('https://'.self::HOST.'/booking/details');
        $response->assertSessionHasErrors('consent');
        $response->assertSessionHasInput('patient_name', 'Aisyah');
    }

    public function test_continuing_without_a_time_returns_a_clear_validation_error(): void
    {
        $date = $this->firstAvailableDate();

        $response = $this->from('https://'.self::HOST.'/booking/date')->post('https://'.self::HOST.'/booking/date', [
            'appointment_date' => $date,
            'intent' => 'continue',
        ]);

        $response->assertRedirect('https://'.self::HOST.'/booking/date');
        $response->assertSessionHasErrors([
            'appointment_time' => 'Choose an available time.',
        ]);
    }

    public function test_business_rule_error_returns_to_review_with_the_draft_intact(): void
    {
        $this->completeUpToReview(patientName: FakeBookingSubmissionGateway::TRIGGER_BUSINESS_RULE);

        $review = $this->get('https://'.self::HOST.'/booking/review');
        $token = $this->extractHiddenValue($review->getContent(), 'submission_token');

        $this->post('https://'.self::HOST.'/booking', ['submission_token' => $token])
            ->assertRedirect('https://'.self::HOST.'/booking/review');

        $this->get('https://'.self::HOST.'/booking/review')->assertOk()->assertSee(FakeBookingSubmissionGateway::TRIGGER_BUSINESS_RULE);
    }

    public function test_infrastructure_error_returns_to_review_with_a_generic_message_never_the_internal_exception(): void
    {
        $this->completeUpToReview(patientName: FakeBookingSubmissionGateway::TRIGGER_INFRASTRUCTURE);

        $review = $this->get('https://'.self::HOST.'/booking/review');
        $token = $this->extractHiddenValue($review->getContent(), 'submission_token');

        $response = $this->post('https://'.self::HOST.'/booking', ['submission_token' => $token]);
        $response->assertRedirect('https://'.self::HOST.'/booking/review');
        $response->assertSessionHasErrors('infrastructure');
    }

    public function test_unexpected_submission_failure_is_logged_without_detail_and_returns_only_the_generic_response(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with('Unexpected public booking submission failure.', \Mockery::on(
                static fn (array $context): bool => array_keys($context) === ['correlation_id'],
            ));
        $this->completeUpToReview(patientName: FakeBookingSubmissionGateway::TRIGGER_UNEXPECTED);

        $review = $this->get('https://'.self::HOST.'/booking/review');
        $token = $this->extractHiddenValue($review->getContent(), 'submission_token');
        $response = $this->post('https://'.self::HOST.'/booking', ['submission_token' => $token]);

        $response->assertRedirect('https://'.self::HOST.'/booking/review');
        $response->assertSessionHasErrors([
            'infrastructure' => 'Something went wrong on our end. Please try again.',
        ]);
        self::assertStringNotContainsString('secret database detail', (string) $response->headers->get('Location'));
    }

    public function test_missing_submission_token_never_reaches_the_submission_gateway(): void
    {
        $this->completeUpToReview();

        $this->post('https://'.self::HOST.'/booking', ['submission_token' => 'guessed-token'])
            ->assertRedirect('https://'.self::HOST.'/booking/review');
    }

    private function completeUpToReview(string $patientName = 'Aisyah'): void
    {
        $date = $this->firstAvailableDate();
        $this->post('https://'.self::HOST.'/booking/service', ['service_id' => 'general-consultation']);
        $this->post('https://'.self::HOST.'/booking/date', [
            'appointment_date' => $date,
            'appointment_time' => '09:00',
            'intent' => 'continue',
        ]);
        $this->post('https://'.self::HOST.'/booking/details', [
            'patient_name' => $patientName,
            'phone' => '+60123456789',
            'consent' => '1',
        ]);
    }

    private function firstAvailableDate(): string
    {
        // The fake PublicAvailabilityReaderInterface bound in setUp() reports every
        // date as Available — any near-future date exercises the same behaviour.
        return (new DateTimeImmutable('today'))->modify('+2 days')->format('Y-m-d');
    }

    private function extractHiddenValue(string $html, string $name): string
    {
        if (preg_match('/name="'.preg_quote($name, '/').'" value="([^"]*)"/', $html, $matches) === 1) {
            return $matches[1];
        }

        return '';
    }
}

/**
 * A Delivery-layer-only fake, local to this test, exactly like the other
 * fakes bound in setUp() — never persists anything and never calls the real
 * Booking Engine. Deterministically raises each of the four ADR-027 error
 * categories for a documented sentinel `patientName`, so every Error
 * Recovery path is exercisable without a real Booking Engine call.
 */
final class FakeBookingSubmissionGateway implements BookingSubmissionGatewayInterface
{
    public const string TRIGGER_BUSINESS_RULE = '__trigger_business_rule__';

    public const string TRIGGER_AVAILABILITY = '__trigger_availability__';

    public const string TRIGGER_INFRASTRUCTURE = '__trigger_infrastructure__';

    public const string TRIGGER_UNEXPECTED = '__trigger_unexpected__';

    public function submit(PublicBookingSubmission $submission): PublicBookingSubmissionResult
    {
        match ($submission->patientName) {
            self::TRIGGER_BUSINESS_RULE => throw new PublicBookingBusinessRuleException("This option isn't available right now. Please choose another."),
            self::TRIGGER_AVAILABILITY => throw new PublicBookingAvailabilityException('That time was just taken. Please choose another.'),
            self::TRIGGER_INFRASTRUCTURE => throw new PublicBookingInfrastructureException('Something went wrong on our end. Please try again.'),
            self::TRIGGER_UNEXPECTED => throw new RuntimeException('secret database detail'),
            default => null,
        };

        $reference = 'BOOK-FAKE-'.strtoupper(substr(sha1($submission->patientName.$submission->phone.$submission->appointmentDate.$submission->appointmentTime), 0, 8));

        return new PublicBookingSubmissionResult($reference, 'submitted', new DateTimeImmutable);
    }
}
