<?php

declare(strict_types=1);

namespace Tests\Contract\Modules\SubscriptionBilling\Persistence;

use App\Modules\SubscriptionBilling\Contracts\Repositories\BillingOptionRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Repositories\CapabilityDefinitionRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Repositories\PlanOfferingRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Repositories\PlanRepositoryInterface;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresBillingOptionRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresCapabilityDefinitionRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresPlanOfferingRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresPlanRepository;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class CommercialCatalogueRepositoryContractTest extends TestCase
{
    public function test_postgres_adapters_implement_the_approved_repository_contracts(): void
    {
        self::assertTrue(is_subclass_of(PostgresPlanRepository::class, PlanRepositoryInterface::class));
        self::assertTrue(is_subclass_of(PostgresBillingOptionRepository::class, BillingOptionRepositoryInterface::class));
        self::assertTrue(is_subclass_of(PostgresPlanOfferingRepository::class, PlanOfferingRepositoryInterface::class));
        self::assertTrue(is_subclass_of(PostgresCapabilityDefinitionRepository::class, CapabilityDefinitionRepositoryInterface::class));
    }

    public function test_repository_contract_methods_remain_narrow_and_stable(): void
    {
        self::assertSame(['findById', 'findByCode', 'save', 'existsByCode'], $this->methodNames(PlanRepositoryInterface::class));
        self::assertSame(['findById', 'findByCode', 'save', 'existsByCode'], $this->methodNames(BillingOptionRepositoryInterface::class));
        self::assertSame(['findById', 'save', 'findAvailableForDate', 'findByPlan'], $this->methodNames(PlanOfferingRepositoryInterface::class));
        self::assertSame(['findById', 'findByKey', 'save', 'existsByKey'], $this->methodNames(CapabilityDefinitionRepositoryInterface::class));
    }

    private function methodNames(string $interface): array
    {
        return array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass($interface))->getMethods(),
        );
    }
}
