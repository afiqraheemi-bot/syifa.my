<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Commercial;

use App\Modules\ClinicRegistration\Contracts\Data\ClinicRegistrationData;
use App\Modules\ClinicRegistration\Contracts\Queries\ClinicRegistrationQueryInterface;
use App\Modules\Commercial\Application\CommercialOfferIdentifierGeneratorInterface;
use App\Modules\Commercial\Contracts\Events\CommercialOfferEventPublisherInterface;
use App\Modules\Commercial\Contracts\ReferenceData\PlanOfferingQueryInterface;
use App\Modules\Commercial\Contracts\ReferenceData\PlanOfferingReferenceData;
use App\Modules\Commercial\Contracts\Repositories\CommercialOfferRepositoryInterface;
use App\Modules\Commercial\Contracts\Transactions\CommercialTransactionInterface;
use App\Modules\Commercial\Domain\CommercialOffer;
use App\Modules\Commercial\Domain\ValueObjects\ClinicRegistrationReference;
use App\Modules\Commercial\Domain\ValueObjects\CommercialOfferId;
use App\Modules\Commercial\Domain\ValueObjects\CommercialOfferStatus;
use App\Modules\Commercial\Domain\ValueObjects\PlatformIdentityReference;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipal;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipalResolverInterface;
use App\Modules\PlatformAdministration\Domain\AuditEntry\AuditEntry;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditEntryId;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use DateTimeImmutable;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class CommercialOfferApiTest extends TestCase
{
    private FeatureInMemoryCommercialOfferRepository $repository;

    private FeatureCommercialAuditRecorder $audit;

    private FeatureMutablePlatformPrincipalResolver $principals;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new FeatureInMemoryCommercialOfferRepository;
        $this->audit = new FeatureCommercialAuditRecorder;
        $this->principals = new FeatureMutablePlatformPrincipalResolver(new PlatformPrincipal(
            $this->uuid(2),
            'clinic_owner',
            'Clinic Owner',
        ));

        $this->app->instance(CommercialOfferRepositoryInterface::class, $this->repository);
        $this->app->instance(PlanOfferingQueryInterface::class, new FeaturePlanOfferingQuery([$this->offering()]));
        $this->app->instance(AuditEntryRecorderInterface::class, $this->audit);
        $this->app->instance(CommercialOfferEventPublisherInterface::class, new FeatureCommercialEventPublisher);
        $this->app->instance(CommercialTransactionInterface::class, new FeatureCommercialTransaction);
        $this->app->instance(CommercialOfferIdentifierGeneratorInterface::class, new FeatureCommercialOfferIdentifierGenerator([$this->uuid(10)]));
        $this->app->instance(PlatformPrincipalResolverInterface::class, $this->principals);
        $this->app->instance(ClinicRegistrationQueryInterface::class, new FeatureClinicRegistrationQuery($this->uuid(3), $this->uuid(6)));
    }

    public function test_platform_identity_cannot_start_initial_acquisition(): void
    {
        $this->getJson('/api/v1/commercial/available-offers')
            ->assertOk()
            ->assertJsonPath('data.0.plan_offering_id', 'offering-basic-monthly');

        $this->postJson('/api/v1/commercial/offers', [
            'clinic_registration_id' => $this->uuid(3),
            'plan_offering_id' => 'offering-basic-monthly',
        ])->assertNotFound();

        self::assertSame([], $this->audit->entries);
    }

    public function test_public_access_is_not_allowed(): void
    {
        $this->principals->principal = null;

        $this->getJson('/api/v1/commercial/available-offers')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/problem+json');
    }

    public function test_routes_are_authenticated_platform_routes_only(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(static fn ($route): bool => str_starts_with($route->uri(), 'api/v1/commercial'))
            ->map(static fn ($route): array => [$route->methods(), $route->uri(), $route->getName(), $route->gatherMiddleware()])
            ->values()
            ->all();

        self::assertCount(4, $routes);

        foreach ($routes as $route) {
            self::assertContains('throttle:platform.session', $route[3]);
        }
    }

    private function offering(): PlanOfferingReferenceData
    {
        return new PlanOfferingReferenceData(
            'offering-basic-monthly',
            'plan-basic',
            'monthly',
            'Basic',
            'Monthly',
            3000,
            'MYR',
            '2026-07-21',
            '2026-08-20',
            'catalogue-v1',
            'capability-v1',
            1,
        );
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}

final class FeatureMutablePlatformPrincipalResolver implements PlatformPrincipalResolverInterface
{
    public function __construct(public ?PlatformPrincipal $principal) {}

    public function resolve(DateTimeImmutable $resolvedAt): ?PlatformPrincipal
    {
        return $this->principal;
    }
}

final class FeaturePlanOfferingQuery implements PlanOfferingQueryInterface
{
    /**
     * @param  list<PlanOfferingReferenceData>  $offerings
     */
    public function __construct(private array $offerings) {}

    public function listAvailable(string $effectiveDate): array
    {
        return $this->offerings;
    }

    public function resolveForCheckout(string $planOfferingId, string $effectiveDate): ?PlanOfferingReferenceData
    {
        foreach ($this->offerings as $offering) {
            if ($offering->planOfferingId === $planOfferingId) {
                return $offering;
            }
        }

        return null;
    }
}

final class FeatureClinicRegistrationQuery implements ClinicRegistrationQueryInterface
{
    public function __construct(private string $registrationId, private ?string $reservedTenantId) {}

    public function currentForPlatformIdentity(string $platformIdentityId): ?ClinicRegistrationData
    {
        return new ClinicRegistrationData(
            $this->registrationId,
            $platformIdentityId,
            'submitted',
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $this->registrationId,
            $this->reservedTenantId,
            null,
            '2026-07-21T00:00:00+00:00',
            null,
            null,
            null,
            1,
            [],
        );
    }

    public function currentForTrackingCredential(string $trackingCredential): ?ClinicRegistrationData
    {
        return $this->currentForPlatformIdentity($trackingCredential);
    }
}

final class FeatureCommercialOfferIdentifierGenerator implements CommercialOfferIdentifierGeneratorInterface
{
    /**
     * @param  list<string>  $ids
     */
    public function __construct(private array $ids) {}

    public function generate(): string
    {
        return array_shift($this->ids) ?? '00000000-0000-4000-8000-000000999999';
    }
}

final class FeatureCommercialEventPublisher implements CommercialOfferEventPublisherInterface
{
    public function publish(array $events): void
    {
        //
    }
}

final class FeatureCommercialTransaction implements CommercialTransactionInterface
{
    public function run(callable $operation): mixed
    {
        return $operation();
    }
}

final class FeatureCommercialAuditRecorder implements AuditEntryRecorderInterface
{
    /**
     * @var list<AuditEntryData>
     */
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

final class FeatureInMemoryCommercialOfferRepository implements CommercialOfferRepositoryInterface
{
    /**
     * @var array<string, CommercialOffer>
     */
    private array $offers = [];

    public function find(CommercialOfferId $commercialOfferId): ?CommercialOffer
    {
        return $this->offers[$commercialOfferId->value] ?? null;
    }

    public function findCurrentForPlatformIdentity(PlatformIdentityReference $platformIdentity): ?CommercialOffer
    {
        foreach ($this->offers as $offer) {
            if ($offer->platformIdentity?->value === $platformIdentity->value && $offer->status === CommercialOfferStatus::Prepared) {
                return $offer;
            }
        }

        return null;
    }

    public function findCurrentForClinicRegistration(ClinicRegistrationReference $clinicRegistration): ?CommercialOffer
    {
        foreach ($this->offers as $offer) {
            if ($offer->clinicRegistration->value === $clinicRegistration->value
                && in_array($offer->status, [CommercialOfferStatus::Prepared, CommercialOfferStatus::Claimed], true)) {
                return $offer;
            }
        }

        return null;
    }

    public function findInitialAcquisitionForRegistration(
        CommercialOfferId $commercialOfferId,
        ClinicRegistrationReference $clinicRegistration,
    ): ?CommercialOffer {
        $offer = $this->offers[$commercialOfferId->value] ?? null;

        return $offer !== null
            && $offer->platformIdentity === null
            && $offer->clinicRegistration->value === $clinicRegistration->value
                ? $offer
                : null;
    }

    public function save(CommercialOffer $commercialOffer): void
    {
        $commercialOffer->synchronizeVersion($commercialOffer->version() + 1);
        $this->offers[$commercialOffer->id->value] = $commercialOffer;
    }
}
