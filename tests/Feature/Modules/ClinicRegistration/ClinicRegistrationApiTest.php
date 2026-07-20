<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\ClinicRegistration;

use App\Modules\ClinicRegistration\Application\ClinicRegistrationIdentifierGeneratorInterface;
use App\Modules\ClinicRegistration\Contracts\Events\ClinicRegistrationEventPublisherInterface;
use App\Modules\ClinicRegistration\Contracts\Repositories\ClinicRegistrationRepositoryInterface;
use App\Modules\ClinicRegistration\Domain\ClinicRegistration;
use App\Modules\ClinicRegistration\Domain\ValueObjects\PlatformIdentityReference;
use App\Modules\ClinicRegistration\Domain\ValueObjects\RegistrationId;
use App\Modules\ClinicRegistration\Domain\ValueObjects\RegistrationStatus;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipal;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipalResolverInterface;
use App\Modules\PlatformAdministration\Domain\AuditEntry\AuditEntry;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditEntryId;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use DateTimeImmutable;
use Tests\TestCase;

final class ClinicRegistrationApiTest extends TestCase
{
    private ApiInMemoryClinicRegistrationRepository $repository;

    private ApiRecordingAuditEntryRecorder $audit;

    private ApiMutablePlatformPrincipalResolver $principals;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('session.driver', 'array');

        $this->repository = new ApiInMemoryClinicRegistrationRepository;
        $this->audit = new ApiRecordingAuditEntryRecorder;
        $this->principals = new ApiMutablePlatformPrincipalResolver(new PlatformPrincipal(
            $this->uuid(21),
            'clinic_owner',
            'Clinic Owner',
        ));

        $this->app->instance(ClinicRegistrationRepositoryInterface::class, $this->repository);
        $this->app->instance(AuditEntryRecorderInterface::class, $this->audit);
        $this->app->instance(ClinicRegistrationEventPublisherInterface::class, new ApiRecordingEventPublisher);
        $this->app->instance(PlatformPrincipalResolverInterface::class, $this->principals);
        $this->app->instance(ClinicRegistrationIdentifierGeneratorInterface::class, new ApiSequentialIdentifierGenerator([$this->uuid(31)]));
    }

    public function test_platform_identity_can_manage_current_registration_flow(): void
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
    }

    public function test_http_validation_rejects_add_on_and_tenant_payloads(): void
    {
        $this->postJson('/api/v1/clinic-registrations', [
            'tenant_id' => $this->uuid(55),
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

    public function test_missing_platform_principal_fails_closed(): void
    {
        $this->principals->principal = null;

        $this->postJson('/api/v1/clinic-registrations')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/problem+json');
    }

    public function test_routes_are_identity_bound_and_named(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(static fn ($route): bool => str_starts_with($route->uri(), 'api/v1/clinic-registrations'))
            ->map(static fn ($route): array => [$route->methods(), $route->uri(), $route->getName(), $route->gatherMiddleware()])
            ->values()
            ->all();

        self::assertCount(5, $routes);
        self::assertSame('clinic-registration.store', $routes[0][2]);
        self::assertSame('clinic-registration.current.show', $routes[1][2]);
        self::assertSame('clinic-registration.current.update', $routes[2][2]);
        self::assertSame('clinic-registration.current.submit', $routes[3][2]);
        self::assertSame('clinic-registration.current.cancel', $routes[4][2]);

        foreach ($routes as $route) {
            self::assertContains('throttle:platform.session', $route[3]);
        }
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}

final class ApiMutablePlatformPrincipalResolver implements PlatformPrincipalResolverInterface
{
    public function __construct(public ?PlatformPrincipal $principal) {}

    public function resolve(DateTimeImmutable $resolvedAt): ?PlatformPrincipal
    {
        return $this->principal;
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
                && in_array($registration->status, [RegistrationStatus::Draft, RegistrationStatus::Submitted], true)
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
}
