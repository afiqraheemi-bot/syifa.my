<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\SubscriptionBilling\Presentation\Http;

use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreateBillingOptionService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreateCapabilityDefinitionService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreatePlanOfferingService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreatePlanService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions\CommercialCatalogueVersionMismatchException;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\GetPlanService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ListBillingOptionsService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ListCapabilityDefinitionsService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ListPlanOfferingsService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ListPlansService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\RetirePlanService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\UpdatePlanDetailsService;
use App\Modules\SubscriptionBilling\Contracts\Authorization\CommercialCatalogueAuthorizationDecision;
use App\Modules\SubscriptionBilling\Contracts\Authorization\CommercialCatalogueAuthorizationInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\BillingOptionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CapabilityDefinitionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\OffsetPaginationInput;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\OffsetPaginationMeta;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\PaginatedBillingOptionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\PaginatedCapabilityDefinitionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\PaginatedPlanData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\PaginatedPlanOfferingData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PlanData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PlanOfferingData;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Exceptions\StaleCommercialCatalogueWriteException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase as LaravelTestCase;

final class CommercialCatalogueHttpDeliveryTest extends LaravelTestCase
{
    private const string BASE = 'https://clinic.app.syifa.my/api/v1/platform/commercial-catalogue';

    public function test_every_approved_route_fails_closed_without_authorization(): void
    {
        foreach ($this->approvedRoutes() as [$method, $uri, $payload]) {
            $response = $this->call($method, $uri, $payload ?? []);
            $response->assertStatus(403);
            $response->assertHeader('Content-Type', 'application/problem+json');
            $response->assertJsonStructure(['type', 'title', 'status', 'detail', 'correlation_id']);
        }
    }

    public function test_malformed_route_identifiers_fail_closed_at_routing(): void
    {
        $this->getJson(self::BASE.'/plans/not-a-uuid')->assertNotFound();
    }

    #[DataProvider('collectionRoutes')]
    public function test_collection_routes_serialize_pagination_envelopes_when_authorized(string $uri, string $serviceClass, object $service, array $expectedData): void
    {
        $this->allowAuthorization();
        $this->app->instance($serviceClass, $service);

        $response = $this->getJson($uri.'?page=1&per_page=25');
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertJsonPath('data.0', $expectedData);
        $response->assertJsonPath('meta.current_page', 1);
        $response->assertJsonPath('meta.per_page', 25);
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('links.first', ltrim((string) parse_url($uri, PHP_URL_PATH), '/').'?page=1&per_page=25');
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/i', (string) $response->json('correlation_id'));
    }

    #[DataProvider('storeRoutes')]
    public function test_store_routes_expose_versioned_resource_envelopes_when_authorized(string $uri, string $serviceClass, object $service, array $payload, string $resourceKey): void
    {
        $this->allowAuthorization();
        $this->app->instance($serviceClass, $service);

        $response = $this->postJson($uri, $payload);
        $response->assertCreated();
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertJsonPath("data.$resourceKey", match ($resourceKey) {
            'plan_id' => '11111111-1111-4111-8111-111111111111',
            'billing_option_id' => '22222222-2222-4222-8222-222222222222',
            'capability_key' => 'booking_management',
            'plan_offering_id' => '44444444-4444-4444-8444-444444444444',
            default => null,
        });
        $response->assertJsonPath('data.version', 1);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/i', (string) $response->json('correlation_id'));
    }

    public function test_get_routes_map_not_found_when_authorized(): void
    {
        $this->allowAuthorization();
        $this->app->instance(GetPlanService::class, new class
        {
            public function execute(string $planId): ?PlanData
            {
                return null;
            }
        });

        $this->getJson(self::BASE.'/plans/11111111-1111-4111-8111-111111111111')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json');
    }

    public function test_version_conflicts_and_invalid_lifecycle_fail_closed_as_problem_details_when_authorized(): void
    {
        $this->allowAuthorization();
        $this->app->instance(UpdatePlanDetailsService::class, new class
        {
            public function execute(mixed $command): mixed
            {
                throw new CommercialCatalogueVersionMismatchException('Plan', '11111111-1111-4111-8111-111111111111', 2, 1);
            }
        });

        $this->patchJson(self::BASE.'/plans/11111111-1111-4111-8111-111111111111', [
            'code' => 'starter',
            'name' => 'Starter',
            'description' => 'Starter plan.',
            'display_order' => 1,
            'expected_version' => 2,
            'occurred_at' => '2026-07-15T00:00:00Z',
            'correlation_id' => '00000000-0000-4000-8000-0000000000c1',
        ])->assertStatus(409);

        $this->app->instance(RetirePlanService::class, new class
        {
            public function execute(mixed $command): mixed
            {
                throw new StaleCommercialCatalogueWriteException('Stale write.');
            }
        });

        $this->postJson(self::BASE.'/plans/11111111-1111-4111-8111-111111111111/retire', [
            'expected_version' => 2,
            'occurred_at' => '2026-07-15T00:00:00Z',
            'correlation_id' => '00000000-0000-4000-8000-0000000000c2',
        ])->assertStatus(409);
    }

    /**
     * @return iterable<string, array{0:string,1:string,2:array<string, mixed>}>
     */
    public static function collectionRoutes(): iterable
    {
        $plan = new PlanData('11111111-1111-4111-8111-111111111111', 'starter', 'Starter', 'Starter plan.', 'draft', 1, '2026-07-15T00:00:00Z', '2026-07-15T00:00:00Z');
        $billingOption = new BillingOptionData('22222222-2222-4222-8222-222222222222', 'annual', 'Annual', 'available', 'recurring', 'year', 1, '2026-07-01', null, 1);
        $capability = new CapabilityDefinitionData('33333333-3333-4333-8333-333333333333', 'booking_management', 'Booking Management', 'Booking controls.', 'Commercial meaning.', 'active');
        $planOffering = new PlanOfferingData('44444444-4444-4444-8444-444444444444', '11111111-1111-4111-8111-111111111111', '22222222-2222-4222-8222-222222222222', 129900, 'MYR', 'draft', '2026-07-01', null, '1', 'catalogue:starter', 1);

        return [
            'plans' => [
                self::BASE.'/plans',
                ListPlansService::class,
                new class(new PaginatedPlanData([$plan], new OffsetPaginationMeta(1, 25, 1, 1, 1, 1)))
                {
                    public function __construct(private PaginatedPlanData $result) {}

                    public function execute(OffsetPaginationInput $pagination): PaginatedPlanData
                    {
                        return $this->result;
                    }
                },
                ['plan_id' => '11111111-1111-4111-8111-111111111111', 'code' => 'starter', 'name' => 'Starter', 'description' => 'Starter plan.', 'status' => 'draft', 'display_order' => 1, 'created_at' => '2026-07-15T00:00:00Z', 'last_changed_at' => '2026-07-15T00:00:00Z', 'version' => 1],
            ],
            'billing options' => [
                self::BASE.'/billing-options',
                ListBillingOptionsService::class,
                new class(new PaginatedBillingOptionData([$billingOption], new OffsetPaginationMeta(1, 25, 1, 1, 1, 1)))
                {
                    public function __construct(private PaginatedBillingOptionData $result) {}

                    public function execute(OffsetPaginationInput $pagination): PaginatedBillingOptionData
                    {
                        return $this->result;
                    }
                },
                ['billing_option_id' => '22222222-2222-4222-8222-222222222222', 'code' => 'annual', 'name' => 'Annual', 'availability' => 'available', 'recurrence_classification' => 'recurring', 'interval_unit' => 'year', 'interval_count' => 1, 'effective_start' => '2026-07-01', 'display_order' => 1, 'version' => 1],
            ],
            'capabilities' => [
                self::BASE.'/capabilities',
                ListCapabilityDefinitionsService::class,
                new class(new PaginatedCapabilityDefinitionData([$capability], new OffsetPaginationMeta(1, 25, 1, 1, 1, 1)))
                {
                    public function __construct(private PaginatedCapabilityDefinitionData $result) {}

                    public function execute(OffsetPaginationInput $pagination): PaginatedCapabilityDefinitionData
                    {
                        return $this->result;
                    }
                },
                ['capability_id' => '33333333-3333-4333-8333-333333333333', 'capability_key' => 'booking_management', 'name' => 'Booking Management', 'description' => 'Booking controls.', 'commercial_meaning' => 'Commercial meaning.', 'status' => 'active', 'version' => 1],
            ],
            'plan offerings' => [
                self::BASE.'/plan-offerings',
                ListPlanOfferingsService::class,
                new class(new PaginatedPlanOfferingData([$planOffering], new OffsetPaginationMeta(1, 25, 1, 1, 1, 1)))
                {
                    public function __construct(private PaginatedPlanOfferingData $result) {}

                    public function execute(OffsetPaginationInput $pagination): PaginatedPlanOfferingData
                    {
                        return $this->result;
                    }
                },
                ['plan_offering_id' => '44444444-4444-4444-8444-444444444444', 'plan_id' => '11111111-1111-4111-8111-111111111111', 'billing_option_id' => '22222222-2222-4222-8222-222222222222', 'amount_minor' => 129900, 'currency_code' => 'MYR', 'status' => 'draft', 'effective_start' => '2026-07-01', 'configuration_version' => '1', 'capability_configuration_reference' => 'catalogue:starter', 'display_order' => 1],
            ],
        ];
    }

    /**
     * @return iterable<string, array{0:string,1:string,2:object,3:array<string, mixed>,4:string}>
     */
    public static function storeRoutes(): iterable
    {
        return [
            'plan create' => [
                self::BASE.'/plans',
                CreatePlanService::class,
                new class
                {
                    private object $result;

                    public function __construct()
                    {
                        $this->result = (object) [
                            'id' => (object) ['value' => '11111111-1111-4111-8111-111111111111'],
                            'name' => (object) ['value' => 'Starter'],
                            'code' => (object) ['value' => 'starter'],
                            'description' => 'Starter plan.',
                            'lifecycle' => (object) ['status' => (object) ['value' => 'draft']],
                            'displayOrder' => 1,
                            'createdAt' => new \DateTimeImmutable('2026-07-15T00:00:00Z'),
                            'lastChangedAt' => new \DateTimeImmutable('2026-07-15T00:00:00Z'),
                            'version' => 1,
                        ];
                    }

                    public function execute(object $command): object
                    {
                        return $this->result;
                    }

                    public function version(): int
                    {
                        return 1;
                    }
                },
                [
                    'code' => 'starter',
                    'name' => 'Starter',
                    'description' => 'Starter plan.',
                    'display_order' => 1,
                    'expected_version' => 1,
                    'occurred_at' => '2026-07-15T00:00:00Z',
                    'correlation_id' => '00000000-0000-4000-8000-0000000000b1',
                ],
                'plan_id',
            ],
            'billing option create' => [
                self::BASE.'/billing-options',
                CreateBillingOptionService::class,
                new class
                {
                    private object $result;

                    public function __construct()
                    {
                        $this->result = (object) [
                            'billingOptionId' => '22222222-2222-4222-8222-222222222222',
                            'code' => 'annual',
                            'name' => 'Annual',
                            'availability' => 'available',
                            'recurrenceClassification' => 'recurring',
                            'intervalUnit' => 'year',
                            'intervalCount' => 1,
                            'effectiveStart' => '2026-07-01',
                            'effectiveEnd' => null,
                            'displayOrder' => 1,
                            'version' => 1,
                        ];
                    }

                    public function execute(object $command): object
                    {
                        return $this->result;
                    }

                    public function version(): int
                    {
                        return 1;
                    }
                },
                [
                    'code' => 'annual',
                    'name' => 'Annual',
                    'recurrence_classification' => 'recurring',
                    'interval_unit' => 'year',
                    'interval_count' => 1,
                    'effective_start' => '2026-07-01',
                    'effective_end' => null,
                    'display_order' => 1,
                    'expected_version' => 1,
                    'occurred_at' => '2026-07-15T00:00:00Z',
                    'correlation_id' => '00000000-0000-4000-8000-0000000000b1',
                ],
                'billing_option_id',
            ],
            'capability create' => [
                self::BASE.'/capabilities',
                CreateCapabilityDefinitionService::class,
                new class
                {
                    private object $result;

                    public function __construct()
                    {
                        $this->result = (object) [
                            'capabilityId' => '33333333-3333-4333-8333-333333333333',
                            'capabilityKey' => 'booking_management',
                            'name' => 'Booking Management',
                            'description' => 'Booking controls.',
                            'commercialMeaning' => 'Commercial meaning.',
                            'status' => 'draft',
                            'version' => 1,
                        ];
                    }

                    public function execute(object $command): object
                    {
                        return $this->result;
                    }

                    public function version(): int
                    {
                        return 1;
                    }
                },
                [
                    'capability_key' => 'booking_management',
                    'name' => 'Booking Management',
                    'description' => 'Booking controls.',
                    'commercial_meaning' => 'Commercial meaning.',
                    'expected_version' => 1,
                    'occurred_at' => '2026-07-15T00:00:00Z',
                    'correlation_id' => '00000000-0000-4000-8000-0000000000b1',
                ],
                'capability_key',
            ],
            'plan offering create' => [
                self::BASE.'/plan-offerings',
                CreatePlanOfferingService::class,
                new class
                {
                    private object $result;

                    public function __construct()
                    {
                        $this->result = (object) [
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
                            'version' => 1,
                        ];
                    }

                    public function execute(object $command): object
                    {
                        return $this->result;
                    }

                    public function version(): int
                    {
                        return 1;
                    }
                },
                [
                    'plan_id' => '11111111-1111-4111-8111-111111111111',
                    'billing_option_id' => '22222222-2222-4222-8222-222222222222',
                    'amount_minor' => 129900,
                    'currency_code' => 'MYR',
                    'effective_start' => '2026-07-01',
                    'effective_end' => null,
                    'capability_configuration_reference' => 'catalogue:starter',
                    'display_order' => 1,
                    'expected_version' => 1,
                    'occurred_at' => '2026-07-15T00:00:00Z',
                    'correlation_id' => '00000000-0000-4000-8000-0000000000b1',
                ],
                'plan_offering_id',
            ],
        ];
    }

    /**
     * @return list<array{0:string,1:string,2:array<string, mixed>|null}>
     */
    private function approvedRoutes(): array
    {
        return [
            ['GET', self::BASE.'/plans?page=1&per_page=25', null],
            ['GET', self::BASE.'/plans/11111111-1111-4111-8111-111111111111', null],
            ['POST', self::BASE.'/plans', [
                'code' => 'starter',
                'name' => 'Starter',
                'description' => 'Starter plan.',
                'display_order' => 1,
                'expected_version' => 1,
                'occurred_at' => '2026-07-15T00:00:00Z',
                'correlation_id' => '00000000-0000-4000-8000-0000000000d1',
            ]],
            ['PATCH', self::BASE.'/plans/11111111-1111-4111-8111-111111111111', [
                'code' => 'starter',
                'name' => 'Starter',
                'description' => 'Starter plan.',
                'display_order' => 1,
                'expected_version' => 1,
                'occurred_at' => '2026-07-15T00:00:00Z',
                'correlation_id' => '00000000-0000-4000-8000-0000000000d2',
            ]],
            ['POST', self::BASE.'/plans/11111111-1111-4111-8111-111111111111/activate', [
                'expected_version' => 1,
                'occurred_at' => '2026-07-15T00:00:00Z',
                'correlation_id' => '00000000-0000-4000-8000-0000000000d3',
            ]],
            ['POST', self::BASE.'/plans/11111111-1111-4111-8111-111111111111/unavailable', [
                'expected_version' => 1,
                'occurred_at' => '2026-07-15T00:00:00Z',
                'correlation_id' => '00000000-0000-4000-8000-0000000000d4',
            ]],
            ['POST', self::BASE.'/plans/11111111-1111-4111-8111-111111111111/grandfather', [
                'expected_version' => 1,
                'occurred_at' => '2026-07-15T00:00:00Z',
                'correlation_id' => '00000000-0000-4000-8000-0000000000d5',
            ]],
            ['POST', self::BASE.'/plans/11111111-1111-4111-8111-111111111111/retire', [
                'expected_version' => 1,
                'occurred_at' => '2026-07-15T00:00:00Z',
                'correlation_id' => '00000000-0000-4000-8000-0000000000d6',
            ]],
            ['GET', self::BASE.'/billing-options?page=1&per_page=25', null],
            ['GET', self::BASE.'/billing-options/22222222-2222-4222-8222-222222222222', null],
            ['POST', self::BASE.'/billing-options', [
                'code' => 'annual',
                'name' => 'Annual',
                'recurrence_classification' => 'recurring',
                'interval_unit' => 'year',
                'interval_count' => 1,
                'effective_start' => '2026-07-01',
                'effective_end' => null,
                'display_order' => 1,
                'expected_version' => 1,
                'occurred_at' => '2026-07-15T00:00:00Z',
                'correlation_id' => '00000000-0000-4000-8000-0000000000d7',
            ]],
            ['PATCH', self::BASE.'/billing-options/22222222-2222-4222-8222-222222222222', [
                'code' => 'annual',
                'name' => 'Annual',
                'availability' => 'available',
                'recurrence_classification' => 'recurring',
                'interval_unit' => 'year',
                'interval_count' => 1,
                'effective_start' => '2026-07-01',
                'effective_end' => null,
                'display_order' => 1,
                'expected_version' => 1,
                'occurred_at' => '2026-07-15T00:00:00Z',
                'correlation_id' => '00000000-0000-4000-8000-0000000000d8',
            ]],
            ['GET', self::BASE.'/capabilities?page=1&per_page=25', null],
            ['GET', self::BASE.'/capabilities/33333333-3333-4333-8333-333333333333', null],
            ['POST', self::BASE.'/capabilities', [
                'capability_key' => 'booking_management',
                'name' => 'Booking Management',
                'description' => 'Booking controls.',
                'commercial_meaning' => 'Commercial meaning.',
                'expected_version' => 1,
                'occurred_at' => '2026-07-15T00:00:00Z',
                'correlation_id' => '00000000-0000-4000-8000-0000000000d9',
            ]],
            ['PATCH', self::BASE.'/capabilities/33333333-3333-4333-8333-333333333333', [
                'capability_key' => 'booking_management',
                'name' => 'Booking Management',
                'description' => 'Booking controls.',
                'commercial_meaning' => 'Commercial meaning.',
                'expected_version' => 1,
                'occurred_at' => '2026-07-15T00:00:00Z',
                'correlation_id' => '00000000-0000-4000-8000-0000000000da',
            ]],
            ['POST', self::BASE.'/capabilities/33333333-3333-4333-8333-333333333333/activate', [
                'expected_version' => 1,
                'occurred_at' => '2026-07-15T00:00:00Z',
                'correlation_id' => '00000000-0000-4000-8000-0000000000db',
            ]],
            ['POST', self::BASE.'/capabilities/33333333-3333-4333-8333-333333333333/deprecate', [
                'expected_version' => 1,
                'occurred_at' => '2026-07-15T00:00:00Z',
                'correlation_id' => '00000000-0000-4000-8000-0000000000dc',
            ]],
            ['POST', self::BASE.'/capabilities/33333333-3333-4333-8333-333333333333/retire', [
                'expected_version' => 1,
                'occurred_at' => '2026-07-15T00:00:00Z',
                'correlation_id' => '00000000-0000-4000-8000-0000000000dd',
            ]],
            ['GET', self::BASE.'/plan-offerings?page=1&per_page=25', null],
            ['GET', self::BASE.'/plan-offerings/44444444-4444-4444-8444-444444444444', null],
            ['POST', self::BASE.'/plan-offerings', [
                'plan_id' => '11111111-1111-4111-8111-111111111111',
                'billing_option_id' => '22222222-2222-4222-8222-222222222222',
                'amount_minor' => 129900,
                'currency_code' => 'MYR',
                'effective_start' => '2026-07-01',
                'effective_end' => null,
                'capability_configuration_reference' => 'catalogue:starter',
                'display_order' => 1,
                'expected_version' => 1,
                'occurred_at' => '2026-07-15T00:00:00Z',
                'correlation_id' => '00000000-0000-4000-8000-0000000000de',
            ]],
            ['PATCH', self::BASE.'/plan-offerings/44444444-4444-4444-8444-444444444444', [
                'amount_minor' => 129900,
                'currency_code' => 'MYR',
                'effective_start' => '2026-07-01',
                'effective_end' => null,
                'capability_configuration_reference' => 'catalogue:starter',
                'display_order' => 1,
                'expected_version' => 1,
                'occurred_at' => '2026-07-15T00:00:00Z',
                'correlation_id' => '00000000-0000-4000-8000-0000000000df',
            ]],
            ['POST', self::BASE.'/plan-offerings/44444444-4444-4444-8444-444444444444/activate', [
                'expected_version' => 1,
                'occurred_at' => '2026-07-15T00:00:00Z',
                'correlation_id' => '00000000-0000-4000-8000-0000000000e0',
            ]],
            ['POST', self::BASE.'/plan-offerings/44444444-4444-4444-8444-444444444444/unavailable', [
                'expected_version' => 1,
                'occurred_at' => '2026-07-15T00:00:00Z',
                'correlation_id' => '00000000-0000-4000-8000-0000000000e1',
            ]],
            ['POST', self::BASE.'/plan-offerings/44444444-4444-4444-8444-444444444444/grandfather', [
                'expected_version' => 1,
                'occurred_at' => '2026-07-15T00:00:00Z',
                'correlation_id' => '00000000-0000-4000-8000-0000000000e2',
            ]],
            ['POST', self::BASE.'/plan-offerings/44444444-4444-4444-8444-444444444444/retire', [
                'expected_version' => 1,
                'occurred_at' => '2026-07-15T00:00:00Z',
                'correlation_id' => '00000000-0000-4000-8000-0000000000e3',
            ]],
        ];
    }

    private function allowAuthorization(): void
    {
        $this->app->instance(CommercialCatalogueAuthorizationInterface::class, new class implements CommercialCatalogueAuthorizationInterface
        {
            public function authorize(string $action): CommercialCatalogueAuthorizationDecision
            {
                return new CommercialCatalogueAuthorizationDecision(true, '00000000-0000-4000-8000-0000000000aa');
            }
        });
    }
}
