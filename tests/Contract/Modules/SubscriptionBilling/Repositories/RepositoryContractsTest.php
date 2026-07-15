<?php

declare(strict_types=1);

namespace Tests\Contract\Modules\SubscriptionBilling\Repositories;

use App\Modules\SubscriptionBilling\Contracts\Repositories\BillingOptionRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Repositories\CapabilityDefinitionRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Repositories\PlanOfferingRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Repositories\PlanRepositoryInterface;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\CapabilityKey;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\PlanId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\BillingOption;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\CapabilityDefinition;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Plan;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\PlanOffering;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingOptionCode;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingOptionId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\CapabilityId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanCode;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanOfferingId;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Traversable;

final class RepositoryContractsTest extends TestCase
{
    public function test_repository_interfaces_expose_only_narrow_persistence_boundaries(): void
    {
        self::assertSame(
            ['findById', 'findByCode', 'save', 'existsByCode'],
            $this->methodNames(PlanRepositoryInterface::class),
        );
        self::assertSame(
            ['findById', 'findByCode', 'save', 'existsByCode'],
            $this->methodNames(BillingOptionRepositoryInterface::class),
        );
        self::assertSame(
            ['findById', 'save', 'findAvailableForDate', 'findByPlan'],
            $this->methodNames(PlanOfferingRepositoryInterface::class),
        );
        self::assertSame(
            ['findById', 'findByKey', 'save', 'existsByKey'],
            $this->methodNames(CapabilityDefinitionRepositoryInterface::class),
        );
    }

    public function test_repository_interfaces_return_only_domain_objects_or_traversable_sequences(): void
    {
        self::assertSame('?'.Plan::class, $this->returnType(PlanRepositoryInterface::class, 'findById'));
        self::assertSame('?'.Plan::class, $this->returnType(PlanRepositoryInterface::class, 'findByCode'));
        self::assertSame('void', $this->returnType(PlanRepositoryInterface::class, 'save'));
        self::assertSame('bool', $this->returnType(PlanRepositoryInterface::class, 'existsByCode'));

        self::assertSame('?'.BillingOption::class, $this->returnType(BillingOptionRepositoryInterface::class, 'findById'));
        self::assertSame('?'.BillingOption::class, $this->returnType(BillingOptionRepositoryInterface::class, 'findByCode'));
        self::assertSame('void', $this->returnType(BillingOptionRepositoryInterface::class, 'save'));
        self::assertSame('bool', $this->returnType(BillingOptionRepositoryInterface::class, 'existsByCode'));

        self::assertSame('?'.PlanOffering::class, $this->returnType(PlanOfferingRepositoryInterface::class, 'findById'));
        self::assertSame('void', $this->returnType(PlanOfferingRepositoryInterface::class, 'save'));
        self::assertSame(Traversable::class, $this->returnType(PlanOfferingRepositoryInterface::class, 'findAvailableForDate'));
        self::assertSame(Traversable::class, $this->returnType(PlanOfferingRepositoryInterface::class, 'findByPlan'));

        self::assertSame('?'.CapabilityDefinition::class, $this->returnType(CapabilityDefinitionRepositoryInterface::class, 'findById'));
        self::assertSame('?'.CapabilityDefinition::class, $this->returnType(CapabilityDefinitionRepositoryInterface::class, 'findByKey'));
        self::assertSame('void', $this->returnType(CapabilityDefinitionRepositoryInterface::class, 'save'));
        self::assertSame('bool', $this->returnType(CapabilityDefinitionRepositoryInterface::class, 'existsByKey'));
    }

    public function test_repository_interfaces_refer_only_to_approved_domain_value_objects(): void
    {
        self::assertSame(PlanId::class, $this->parameterType(PlanRepositoryInterface::class, 'findById'));
        self::assertSame(PlanCode::class, $this->parameterType(PlanRepositoryInterface::class, 'findByCode'));
        self::assertSame(PlanCode::class, $this->parameterType(PlanRepositoryInterface::class, 'existsByCode'));

        self::assertSame(BillingOptionId::class, $this->parameterType(BillingOptionRepositoryInterface::class, 'findById'));
        self::assertSame(BillingOptionCode::class, $this->parameterType(BillingOptionRepositoryInterface::class, 'findByCode'));
        self::assertSame(BillingOptionCode::class, $this->parameterType(BillingOptionRepositoryInterface::class, 'existsByCode'));

        self::assertSame(PlanOfferingId::class, $this->parameterType(PlanOfferingRepositoryInterface::class, 'findById'));
        self::assertSame(PlanId::class, $this->parameterType(PlanOfferingRepositoryInterface::class, 'findByPlan'));

        self::assertSame(CapabilityId::class, $this->parameterType(CapabilityDefinitionRepositoryInterface::class, 'findById'));
        self::assertSame(CapabilityKey::class, $this->parameterType(CapabilityDefinitionRepositoryInterface::class, 'findByKey'));
        self::assertSame(CapabilityKey::class, $this->parameterType(CapabilityDefinitionRepositoryInterface::class, 'existsByKey'));
    }

    private function methodNames(string $interface): array
    {
        return array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass($interface))->getMethods(),
        );
    }

    private function returnType(string $interface, string $method): string
    {
        $type = (new ReflectionClass($interface))->getMethod($method)->getReturnType();
        self::assertNotNull($type);

        return ($type->allowsNull() ? '?' : '').$type->getName();
    }

    private function parameterType(string $interface, string $method): string
    {
        $parameter = (new ReflectionClass($interface))->getMethod($method)->getParameters()[0];
        $type = $parameter->getType();
        self::assertNotNull($type);

        return $type->getName();
    }
}
