<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\ClinicRegistration;

use App\Modules\ClinicRegistration\Application\ClinicRegistrationIdentifierGeneratorInterface;
use App\Modules\ClinicRegistration\Contracts\Authentication\ClinicRegistrationAccessInterface;
use App\Modules\ClinicRegistration\Contracts\Checkout\CompleteLocalDemoAcquisitionInterface;
use App\Modules\ClinicRegistration\Contracts\Checkout\PublicInitialAcquisitionCheckoutInterface;
use App\Modules\ClinicRegistration\Contracts\Checkout\PublicInitialAcquisitionCheckoutResult;
use App\Modules\ClinicRegistration\Contracts\Checkout\StartPublicInitialAcquisitionCheckoutCommand;
use App\Modules\ClinicRegistration\Contracts\Events\ClinicRegistrationEventPublisherInterface;
use App\Modules\ClinicRegistration\Contracts\Repositories\ClinicRegistrationRepositoryInterface;
use App\Modules\ClinicRegistration\Contracts\Tracking\RegistrationTrackingCredentialInterface;
use App\Modules\ClinicRegistration\Contracts\Tracking\RegistrationTrackingCredentialWriterInterface;
use App\Modules\ClinicRegistration\Domain\ClinicRegistration;
use App\Modules\ClinicRegistration\Domain\ValueObjects\PlatformIdentityReference;
use App\Modules\ClinicRegistration\Domain\ValueObjects\RegistrationDecisionOutcome;
use App\Modules\ClinicRegistration\Domain\ValueObjects\RegistrationId;
use App\Modules\ClinicRegistration\Infrastructure\Tracking\LaravelRegistrationTrackingCredential;
use App\Modules\Commercial\Contracts\ReferenceData\PlanOfferingQueryInterface;
use App\Modules\Commercial\Contracts\ReferenceData\PlanOfferingReferenceData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Domain\AuditEntry\AuditEntry;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditEntryId;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use App\Modules\PlatformAdministration\Presentation\Http\Middleware\AuthenticatePlatformSessionMiddleware;
use App\Modules\WebsiteBuilder\Contracts\PublicAddress\PublicWebsiteAddressAvailabilityInterface;
use Tests\TestCase;

final class ClinicRegistrationApiTest extends TestCase
{
    private ApiInMemoryClinicRegistrationRepository $repository;

    private ApiRecordingAuditEntryRecorder $audit;

    private ApiRegistrationTrackingCredential $tracking;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('session.driver', 'array');

        $this->repository = new ApiInMemoryClinicRegistrationRepository;
        $this->audit = new ApiRecordingAuditEntryRecorder;
        $this->tracking = new ApiRegistrationTrackingCredential($this->uuid(21));

        $this->app->instance(ClinicRegistrationRepositoryInterface::class, $this->repository);
        $this->app->instance(AuditEntryRecorderInterface::class, $this->audit);
        $this->app->instance(ClinicRegistrationEventPublisherInterface::class, new ApiRecordingEventPublisher);
        $this->app->instance(RegistrationTrackingCredentialInterface::class, $this->tracking);
        $this->app->instance(RegistrationTrackingCredentialWriterInterface::class, $this->tracking);
        $this->app->instance(ClinicRegistrationAccessInterface::class, new ApiClinicRegistrationAccess);
        $this->app->instance(
            PublicWebsiteAddressAvailabilityInterface::class,
            new class implements PublicWebsiteAddressAvailabilityInterface
            {
                public function available(string $subdomain, string $registrationOwner): bool
                {
                    return true;
                }
            },
        );
        $this->app->instance(ClinicRegistrationIdentifierGeneratorInterface::class, new ApiSequentialIdentifierGenerator([$this->uuid(31)]));
    }

    public function test_public_prospect_can_manage_registration_with_server_owned_tracking_credential(): void
    {
        $this->postJson('/api/v1/clinic-registrations')
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.id', $this->uuid(31));

        $this->getJson('/api/v1/clinic-registrations/current')
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->patchJson('/api/v1/clinic-registrations/current', [
            'clinic_name' => 'Klinik Syifa',
            'clinic_email' => 'owner@clinic.test',
            'clinic_phone' => '+60123456789',
            'clinic_address' => '1 Jalan Klinik',
            'preferred_subdomain' => 'klinik-syifa',
            'selected_website_template' => 'SYIFA_ESSENTIAL',
            'selected_plan_offering_reference' => 'offering-basic-monthly',
            'selected_billing_option_reference' => 'monthly',
            'commercial_snapshot_version' => 'catalogue-v1',
            'expected_version' => 1,
            'declarations' => [[
                'key' => 'terms.acceptance',
                'version' => '2026-07-20',
                'accepted_at' => '2026-07-20T00:00:00Z',
            ]],
        ])->assertOk()
            ->assertJsonPath('data.clinic.name', 'Klinik Syifa')
            ->assertJsonPath('data.website_preferences.host', 'klinik-syifa.syifa.my')
            ->assertJsonPath('data.website_preferences.template', 'SYIFA_ESSENTIAL')
            ->assertJsonPath('data.version', 2);

        $this->postJson('/api/v1/clinic-registrations/current/submit', [
            'expected_version' => 2,
        ])->assertOk()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.version', 3);

        $this->postJson('/api/v1/clinic-registrations/current/cancel', [
            'expected_version' => 3,
        ])->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        self::assertSame([
            'clinic_registration.start',
            'clinic_registration.update',
            'clinic_registration.submit',
            'clinic_registration.cancel',
        ], array_map(static fn (AuditEntryData $entry): string => $entry->action, $this->audit->entries));
        self::assertSame(
            [AuditActorType::Anonymous->value],
            array_values(array_unique(array_map(
                static fn (AuditEntryData $entry): string => $entry->actor->type,
                $this->audit->entries,
            ))),
        );
    }

    public function test_public_prospect_can_configure_and_resume_application_access(): void
    {
        $this->postJson('/api/v1/clinic-registrations')->assertCreated();

        $this->patchJson('/api/v1/clinic-registrations/current', [
            'clinic_name' => 'Klinik Login',
            'clinic_email' => 'owner@login.test',
            'clinic_phone' => '+60123456789',
            'clinic_address' => '1 Jalan Login',
            'expected_version' => 1,
            'declarations' => [],
        ])->assertOk();

        $this->postJson('/register/access', [
            'password' => 'secure-password-123',
            'password_confirmation' => 'secure-password-123',
        ])->assertCreated()
            ->assertJsonPath('data.configured', true);

        $this->post('/register/logout')->assertRedirect('/');
        self::assertNull($this->tracking->current());

        $this->postJson('/register/login', [
            'email' => 'owner@login.test',
            'password' => 'wrong-password',
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'Permohonan tidak dapat disahkan.');

        $this->postJson('/register/login', [
            'email' => 'OWNER@LOGIN.TEST',
            'password' => 'secure-password-123',
            'remember' => true,
        ])->assertOk()
            ->assertJsonPath('data.authenticated', true)
            ->assertJsonPath('data.redirect', route('clinic-registration.browser'));

        self::assertSame($this->uuid(21), $this->tracking->current());
        self::assertTrue($this->tracking->remember);
    }

    public function test_http_validation_rejects_add_on_and_tenant_payloads(): void
    {
        $this->postJson('/api/v1/clinic-registrations', [
            'tenant_id' => $this->uuid(55),
            'platform_identity_id' => $this->uuid(56),
            'registration_tracking_credential' => $this->uuid(57),
            'selected_add_on_references' => ['addon'],
        ])->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', 'clinic_registration.validation_failed');
    }

    public function test_stale_version_returns_problem_details_conflict(): void
    {
        $this->postJson('/api/v1/clinic-registrations')->assertCreated();

        $this->patchJson('/api/v1/clinic-registrations/current', [
            'expected_version' => 99,
        ])->assertConflict()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', 'clinic_registration.concurrency_conflict');
    }

    public function test_missing_tracking_credential_fails_closed_for_current_registration(): void
    {
        $this->tracking->credential = null;

        $this->getJson('/api/v1/clinic-registrations/current')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json');
    }

    public function test_routes_are_public_rate_limited_and_named(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(static fn ($route): bool => str_starts_with($route->uri(), 'api/v1/clinic-registrations'))
            ->map(static fn ($route): array => [$route->methods(), $route->uri(), $route->getName(), $route->gatherMiddleware()])
            ->values()
            ->all();

        self::assertCount(6, $routes);
        self::assertSame('clinic-registration.store', $routes[0][2]);
        self::assertSame('clinic-registration.current.show', $routes[1][2]);
        self::assertSame('clinic-registration.current.update', $routes[2][2]);
        self::assertSame('clinic-registration.current.submit', $routes[3][2]);
        self::assertSame('clinic-registration.current.resubmit', $routes[4][2]);
        self::assertSame('clinic-registration.current.cancel', $routes[5][2]);

        foreach ($routes as $route) {
            self::assertContains('throttle:public.default', $route[3]);
            self::assertNotContains(
                AuthenticatePlatformSessionMiddleware::class,
                $route[3],
            );
        }
    }

    public function test_public_browser_entry_creates_once_and_resumes_the_tracked_draft(): void
    {
        $this->app->forgetInstance(RegistrationTrackingCredentialInterface::class);
        $this->app->singleton(
            RegistrationTrackingCredentialInterface::class,
            LaravelRegistrationTrackingCredential::class,
        );
        $this->app->instance(PlanOfferingQueryInterface::class, new class implements PlanOfferingQueryInterface
        {
            public function listAvailable(string $effectiveDate): array
            {
                return [];
            }

            public function resolveForCheckout(string $planOfferingId, string $effectiveDate): ?PlanOfferingReferenceData
            {
                return null;
            }
        });

        $this->get('/register')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('ClinicRegistration/PublicClinicRegistration', false)
                    ->where('registration.id', $this->uuid(31))
                    ->where('registration.status', 'draft')
                    ->where('offers', [])
                    ->where('updateUrl', route('clinic-registration.current.update'))
                    ->where('submitUrl', route('clinic-registration.current.submit'))
                    ->where('cancelUrl', route('clinic-registration.current.cancel'))
                    ->where(
                        'addressAvailabilityUrl',
                        route('clinic-registration.website-address.availability'),
                    )
                    ->where('websiteBaseDomain', 'syifa.my')
                    ->has('templates', 5)
                    ->where('offersUrl', route('clinic-registration.offers')),
            );

        $this->getJson('/register/website-address/availability?subdomain=klinik-baru')
            ->assertOk()
            ->assertJsonPath('available', true);

        $this->get('/register')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page->where('registration.id', $this->uuid(31)),
            );

        self::assertSame(1, $this->repository->count());
    }

    public function test_submitted_public_registration_can_view_only_projected_available_offers(): void
    {
        $this->app->instance(PlanOfferingQueryInterface::class, new class($this->uuid(71)) implements PlanOfferingQueryInterface
        {
            public function __construct(private readonly string $offeringId) {}

            public function listAvailable(string $effectiveDate): array
            {
                return [new PlanOfferingReferenceData(
                    $this->offeringId,
                    'plan-essential',
                    'annual',
                    'Syifa Essential',
                    'Annual',
                    120000,
                    'MYR',
                    $effectiveDate,
                    $effectiveDate,
                    'catalogue-v1',
                    'essential-v1',
                    1,
                )];
            }

            public function resolveForCheckout(string $planOfferingId, string $effectiveDate): ?PlanOfferingReferenceData
            {
                return null;
            }
        });

        $this->postJson('/api/v1/clinic-registrations')->assertCreated();
        $this->patchJson('/api/v1/clinic-registrations/current', [
            'clinic_name' => 'Klinik Syifa',
            'clinic_email' => 'owner@clinic.test',
            'clinic_phone' => '+60123456789',
            'clinic_address' => '1 Jalan Klinik',
            'preferred_subdomain' => 'klinik-syifa-offers',
            'selected_website_template' => 'SYIFA_CARE',
            'selected_plan_offering_reference' => $this->uuid(71),
            'selected_billing_option_reference' => 'annual',
            'commercial_snapshot_version' => 'catalogue-v1',
            'expected_version' => 1,
            'declarations' => [[
                'key' => 'clinic_registration.accuracy',
                'version' => '1',
                'accepted_at' => '2026-07-25T00:00:00Z',
            ]],
        ])->assertOk();
        $this->postJson('/api/v1/clinic-registrations/current/submit', [
            'expected_version' => 2,
        ])->assertOk();
        $registration = $this->repository->find(new RegistrationId($this->uuid(31)));
        self::assertNotNull($registration);
        $registration->startReview($this->uuid(81), new \DateTimeImmutable('2026-07-25T00:01:00Z'));
        $registration->decide(
            $this->uuid(82),
            RegistrationDecisionOutcome::Approved,
            'eligible_clinic',
            null,
            $this->uuid(81),
            new \DateTimeImmutable('2026-07-25T00:02:00Z'),
        );
        $this->repository->save($registration);

        $this->get('/register/offers')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('ClinicRegistration/PublicCommercialOffers', false)
                    ->where('registrationStatus', 'approved')
                    ->where('clinicName', 'Klinik Syifa')
                    ->has('offers', 1)
                    ->where('offers.0.planOfferingId', $this->uuid(71))
                    ->where('offers.0.planName', 'Syifa Essential')
                    ->where('offers.0.billingCycleName', 'Annual')
                    ->where('offers.0.formattedPrice', 'MYR 1,200.00')
                    ->where('selectionUrl', route('clinic-registration.offers.select'))
                    ->where(
                        'offers.0.includedSetup',
                        'Managed website onboarding and initial website setup',
                    ),
            );

        $this->app->instance(
            PublicInitialAcquisitionCheckoutInterface::class,
            new ApiInitialAcquisitionCheckout(
                PublicInitialAcquisitionCheckoutResult::ready('https://toyyibpay.com/bill-code-1'),
            ),
        );
        $this->postJson('/register/offers/selection', [
            'plan_offering_id' => $this->uuid(71),
        ])->assertOk()
            ->assertJsonPath('redirect_action.kind', 'redirect')
            ->assertJsonPath('redirect_action.method', 'GET')
            ->assertJsonPath('redirect_action.destination', 'https://toyyibpay.com/bill-code-1');

        $this->app->instance(
            PublicInitialAcquisitionCheckoutInterface::class,
            new ApiInitialAcquisitionCheckout(PublicInitialAcquisitionCheckoutResult::providerNotReady()),
        );
        $this->postJson('/register/offers/selection', [
            'plan_offering_id' => $this->uuid(71),
        ])->assertStatus(503)
            ->assertJsonPath('type', 'payment.provider_not_ready');

        $demo = new ApiLocalDemoAcquisition;
        $this->app->instance(CompleteLocalDemoAcquisitionInterface::class, $demo);
        $this->postJson('/register/offers/demo-payment')
            ->assertOk()
            ->assertJsonPath('message', 'Demo payment succeeded and provisioning completed.');
        self::assertSame($this->uuid(21), $demo->trackingCredential);
    }

    public function test_initial_offer_selection_rejects_financial_and_authority_payloads(): void
    {
        $this->postJson('/register/offers/selection', [
            'plan_offering_id' => $this->uuid(71),
            'platform_identity_id' => $this->uuid(72),
            'tenant_id' => $this->uuid(73),
            'provider' => 'toyyibpay',
            'amount' => 1,
        ])->assertUnprocessable()
            ->assertJsonPath('type', 'clinic_registration.validation_failed');
    }

    public function test_initial_offer_selection_fails_closed_without_registration_tracking_credential(): void
    {
        $this->tracking->credential = null;

        $this->postJson('/register/offers/selection', [
            'plan_offering_id' => $this->uuid(71),
        ])->assertNotFound();
    }

    public function test_offer_page_fails_closed_without_owned_submitted_registration(): void
    {
        $this->get('/register/offers')->assertNotFound();

        $this->postJson('/api/v1/clinic-registrations')->assertCreated();
        $this->get('/register/offers')->assertNotFound();

        $this->tracking->credential = $this->uuid(99);
        $this->get('/register/offers')->assertNotFound();
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}

final readonly class ApiInitialAcquisitionCheckout implements PublicInitialAcquisitionCheckoutInterface
{
    public function __construct(private PublicInitialAcquisitionCheckoutResult $result) {}

    public function execute(
        StartPublicInitialAcquisitionCheckoutCommand $command,
    ): PublicInitialAcquisitionCheckoutResult {
        return $this->result;
    }
}

final class ApiLocalDemoAcquisition implements CompleteLocalDemoAcquisitionInterface
{
    public ?string $trackingCredential = null;

    public function execute(string $trackingCredential, string $correlationId): void
    {
        $this->trackingCredential = $trackingCredential;
    }
}

final class ApiRegistrationTrackingCredential implements RegistrationTrackingCredentialInterface, RegistrationTrackingCredentialWriterInterface
{
    public bool $remember = false;

    public function __construct(public ?string $credential) {}

    public function current(): ?string
    {
        return $this->credential;
    }

    public function establish(): string
    {
        return $this->credential ??= '00000000-0000-4000-8000-000000000021';
    }

    public function forget(): void
    {
        $this->credential = null;
    }

    public function resume(string $credential, bool $remember = false): void
    {
        $this->credential = $credential;
        $this->remember = $remember;
    }
}

final class ApiClinicRegistrationAccess implements ClinicRegistrationAccessInterface
{
    /** @var array<string, array{email: string, password: string, credential: string}> */
    private array $credentials = [];

    public function configured(string $registrationId): bool
    {
        return isset($this->credentials[$registrationId]);
    }

    public function configure(string $registrationId, string $authoritativeEmail, string $password): void
    {
        $this->credentials[$registrationId] = [
            'email' => mb_strtolower($authoritativeEmail),
            'password' => $password,
            'credential' => '00000000-0000-4000-8000-000000000021',
        ];
    }

    public function authenticate(string $email, string $password): ?string
    {
        foreach ($this->credentials as $credential) {
            if ($credential['email'] === mb_strtolower($email)
                && $credential['password'] === $password) {
                return $credential['credential'];
            }
        }

        return null;
    }
}

final class ApiSequentialIdentifierGenerator implements ClinicRegistrationIdentifierGeneratorInterface
{
    /** @param list<string> $ids */
    public function __construct(private array $ids) {}

    public function generate(): string
    {
        return array_shift($this->ids) ?? '00000000-0000-4000-8000-000000999999';
    }
}

final class ApiRecordingEventPublisher implements ClinicRegistrationEventPublisherInterface
{
    public function publish(array $events): void
    {
        //
    }
}

final class ApiRecordingAuditEntryRecorder implements AuditEntryRecorderInterface
{
    /** @var list<AuditEntryData> */
    public array $entries = [];

    public function record(AuditEntryData $auditEntry): AuditEntry
    {
        $this->entries[] = $auditEntry;

        return AuditEntry::record(
            new AuditEntryId($auditEntry->auditEntryId),
            $auditEntry->occurredAt,
            AuditActorType::from($auditEntry->actor->type),
            $auditEntry->actor->identityId,
            $auditEntry->tenantId,
            $auditEntry->action,
            $auditEntry->target->type,
            $auditEntry->target->id,
            AuditOutcomeType::from($auditEntry->outcome->outcome),
            $auditEntry->outcome->reasonCode,
            $auditEntry->correlationId,
            $auditEntry->safeMetadata,
        );
    }
}

final class ApiInMemoryClinicRegistrationRepository implements ClinicRegistrationRepositoryInterface
{
    /** @var array<string, ClinicRegistration> */
    private array $registrations = [];

    public function find(RegistrationId $registrationId): ?ClinicRegistration
    {
        return $this->registrations[$registrationId->value] ?? null;
    }

    public function findCurrentForPlatformIdentity(PlatformIdentityReference $platformIdentity): ?ClinicRegistration
    {
        foreach ($this->registrations as $registration) {
            if (
                $registration->platformIdentity->value === $platformIdentity->value
            ) {
                return $registration;
            }
        }

        return null;
    }

    public function findByCorrelationReference(string $correlationReference): ?ClinicRegistration
    {
        foreach ($this->registrations as $registration) {
            if ($registration->correlationReference === $correlationReference) {
                return $registration;
            }
        }

        return null;
    }

    public function save(ClinicRegistration $registration): void
    {
        $registration->synchronizeVersion($registration->version() + 1);
        $this->registrations[$registration->id->value] = $registration;
    }

    public function count(): int
    {
        return count($this->registrations);
    }
}
