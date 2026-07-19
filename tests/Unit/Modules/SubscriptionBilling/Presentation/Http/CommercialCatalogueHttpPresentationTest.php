<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SubscriptionBilling\Presentation\Http;

use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions\CommercialCatalogueResourceNotFoundException;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\OffsetPaginationMeta;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidCommercialCatalogueValueException;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidPlanLifecycleTransitionException;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Exceptions\StaleCommercialCatalogueWriteException;
use App\Modules\SubscriptionBilling\Presentation\Http\Collections\PlanCollection;
use App\Modules\SubscriptionBilling\Presentation\Http\Resources\BillingOptionResource;
use App\Modules\SubscriptionBilling\Presentation\Http\Resources\CapabilityDefinitionResource;
use App\Modules\SubscriptionBilling\Presentation\Http\Resources\PlanOfferingResource;
use App\Modules\SubscriptionBilling\Presentation\Http\Resources\PlanResource;
use App\Modules\SubscriptionBilling\Presentation\Http\Responses\CommercialCatalogueErrorResponseMapper;
use App\Modules\SubscriptionBilling\Presentation\Resources\BaseResource;
use Illuminate\Auth\Access\AuthorizationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class CommercialCatalogueHttpPresentationTest extends TestCase
{
    public function test_plan_resource_serializes_read_and_write_payloads(): void
    {
        $read = new PlanResource((object) [
            'planId' => '11111111-1111-4111-8111-111111111111',
            'code' => 'starter',
            'name' => 'Starter',
            'description' => 'Starter plan.',
            'status' => 'draft',
            'displayOrder' => 1,
            'createdAt' => '2026-07-15T00:00:00Z',
            'lastChangedAt' => '2026-07-15T00:00:00Z',
        ]);

        self::assertSame('11111111-1111-4111-8111-111111111111', $read->toArray()['plan_id']);
        self::assertArrayNotHasKey('version', $read->toArray());

        $write = new PlanResource(new class
        {
            public object $id;

            public object $name;

            public object $code;

            public string $description = 'Starter plan.';

            public object $lifecycle;

            public int $displayOrder = 1;

            public \DateTimeImmutable $createdAt;

            public \DateTimeImmutable $lastChangedAt;

            public function __construct()
            {
                $this->id = (object) ['value' => '11111111-1111-4111-8111-111111111111'];
                $this->name = (object) ['value' => 'Starter'];
                $this->code = (object) ['value' => 'starter'];
                $this->lifecycle = (object) ['status' => (object) ['value' => 'active']];
                $this->createdAt = new \DateTimeImmutable('2026-07-15T00:00:00Z');
                $this->lastChangedAt = new \DateTimeImmutable('2026-07-15T00:00:00Z');
            }

            public function version(): int
            {
                return 2;
            }
        });

        self::assertSame(2, $write->toArray()['version']);
        self::assertSame('active', $write->toArray()['status']);
    }

    public function test_other_resources_serialise_the_expected_canonical_fields(): void
    {
        $billingOption = new BillingOptionResource((object) [
            'billingOptionId' => '22222222-2222-4222-8222-222222222222',
            'code' => 'annual',
            'name' => 'Annual',
            'availability' => 'available',
            'recurrenceClassification' => 'recurring',
            'intervalUnit' => 'year',
            'intervalCount' => 1,
            'effectiveStart' => '2026-07-01',
            'effectiveEnd' => null,
            'displayOrder' => 2,
        ]);

        $capability = new CapabilityDefinitionResource((object) [
            'capabilityId' => '33333333-3333-4333-8333-333333333333',
            'capabilityKey' => 'booking_management',
            'name' => 'Booking Management',
            'description' => 'Booking controls.',
            'commercialMeaning' => 'Commercial meaning.',
            'status' => 'active',
        ]);

        $planOffering = new PlanOfferingResource((object) [
            'planOfferingId' => '44444444-4444-4444-8444-444444444444',
            'planId' => '11111111-1111-4111-8111-111111111111',
            'billingOptionId' => '22222222-2222-4222-8222-222222222222',
            'amountMinor' => 129900,
            'currencyCode' => 'MYR',
            'status' => 'draft',
            'effectiveStart' => '2026-07-01',
            'effectiveEnd' => null,
            'configurationVersion' => '1',
            'capabilityConfigurationReference' => 'catalogue:starter',
            'displayOrder' => 1,
        ]);

        self::assertSame('annual', $billingOption->toArray()['code']);
        self::assertSame('booking_management', $capability->toArray()['capability_key']);
        self::assertSame(129900, $planOffering->toArray()['amount_minor']);
    }

    public function test_collection_envelope_builds_deterministic_links(): void
    {
        $resource = new class extends BaseResource
        {
            public function toArray(): array
            {
                return ['id' => 'resource-id'];
            }
        };

        $collection = PlanCollection::fromPagination(
            '/api/v1/platform/commercial-catalogue/plans',
            [$resource],
            new OffsetPaginationMeta(2, 25, 50, 2, 26, 50),
        );

        self::assertSame(['current_page' => 2, 'per_page' => 25, 'total' => 50, 'last_page' => 2, 'from' => 26, 'to' => 50], $collection->meta());
        self::assertSame('/api/v1/platform/commercial-catalogue/plans?page=1&per_page=25', $collection->links()['first']);
        self::assertSame('/api/v1/platform/commercial-catalogue/plans?page=2&per_page=25', $collection->links()['last']);
        self::assertSame('/api/v1/platform/commercial-catalogue/plans?page=1&per_page=25', $collection->links()['previous']);
        self::assertNull($collection->links()['next']);
    }

    public function test_error_mapper_uses_safe_problem_details_statuses(): void
    {
        $mapper = new CommercialCatalogueErrorResponseMapper;

        self::assertSame(404, $mapper->map(new CommercialCatalogueResourceNotFoundException('Plan', 'id'), '00000000-0000-4000-8000-000000000001')->status);
        self::assertSame(403, $mapper->map(new AccessDeniedHttpException('denied'), '00000000-0000-4000-8000-000000000002')->status);
        self::assertSame(409, $mapper->map(new InvalidPlanLifecycleTransitionException('bad transition'), '00000000-0000-4000-8000-000000000003')->status);
        self::assertSame(422, $mapper->map(new InvalidCommercialCatalogueValueException('bad value'), '00000000-0000-4000-8000-000000000004')->status);
        self::assertSame(409, $mapper->map(new StaleCommercialCatalogueWriteException('stale'), '00000000-0000-4000-8000-000000000005')->status);
        self::assertSame(500, $mapper->map(new \PDOException('database unavailable'), '00000000-0000-4000-8000-000000000006')->status);
        self::assertSame(403, $mapper->map(new AuthorizationException('forbidden'), '00000000-0000-4000-8000-000000000007')->status);
    }
}
