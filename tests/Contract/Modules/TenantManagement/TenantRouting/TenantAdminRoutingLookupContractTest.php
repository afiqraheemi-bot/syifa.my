<?php

declare(strict_types=1);

namespace Tests\Contract\Modules\TenantManagement\TenantRouting;

use App\Modules\TenantManagement\Contracts\Authentication\TrustedTenantSelectorInterface;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextResolverInterface;
use App\Modules\TenantManagement\Contracts\TenantRouting\TenantAdminRoutingData;
use App\Modules\TenantManagement\Contracts\TenantRouting\TenantAdminRoutingLookupInterface;
use App\Modules\TenantManagement\Infrastructure\Persistence\Lookups\PostgresTenantAdminRoutingLookup;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

final class TenantAdminRoutingLookupContractTest extends TestCase
{
    public function test_contract_is_narrow_read_only_and_implemented_by_postgres_lookup(): void
    {
        self::assertTrue(is_subclass_of(
            PostgresTenantAdminRoutingLookup::class,
            TenantAdminRoutingLookupInterface::class,
        ));
        $contract = new ReflectionClass(TenantAdminRoutingLookupInterface::class);

        self::assertSame(['resolve'], array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            $contract->getMethods(),
        ));
    }

    public function test_lookup_result_contains_only_minimum_routing_selection_data(): void
    {
        $data = new TenantAdminRoutingData(
            '00000000-0000-4000-8000-000000000001',
            'clinic-one',
            'active',
        );
        $reflection = new ReflectionClass($data);

        self::assertTrue($reflection->isReadOnly());
        self::assertSame(
            ['tenantId', 'routingLabel', 'lifecycleStatus'],
            array_map(static fn ($property): string => $property->getName(), $reflection->getProperties()),
        );
    }

    public function test_provider_binds_only_the_approved_routing_lookup(): void
    {
        self::assertInstanceOf(
            PostgresTenantAdminRoutingLookup::class,
            $this->app->make(TenantAdminRoutingLookupInterface::class),
        );
        self::assertFalse($this->app->bound(TrustedTenantSelectorInterface::class));
        self::assertFalse($this->app->bound(TenantContextResolverInterface::class));
    }
}
