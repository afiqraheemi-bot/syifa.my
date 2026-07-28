<?php

declare(strict_types=1);

namespace Tests\Contract\Modules\TenantManagement\TenantRouting;

use App\Modules\TenantManagement\Contracts\Authentication\PasswordBlocklistInterface;
use App\Modules\TenantManagement\Contracts\Authentication\TrustedTenantSelectorInterface;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextResolverInterface;
use App\Modules\TenantManagement\Contracts\TenantRouting\TenantAdminRoutingData;
use App\Modules\TenantManagement\Contracts\TenantRouting\TenantAdminRoutingLookupInterface;
use App\Modules\TenantManagement\Infrastructure\Persistence\Lookups\PostgresTenantAdminRoutingLookup;
use App\Modules\TenantManagement\Infrastructure\TenantContext\ClinicOwnerTenantContextResolver;
use App\Modules\TenantManagement\Infrastructure\TenantManagementServiceProvider;
use App\Modules\TenantManagement\Infrastructure\TenantRouting\Exceptions\InvalidTenantAdminDomainConfigurationException;
use App\Modules\TenantManagement\Infrastructure\TenantRouting\TenantAdminHostTrustedTenantSelector;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Foundation\Application;
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

    public function test_provider_binds_the_approved_routing_lookup_and_trusted_selector(): void
    {
        self::assertInstanceOf(
            PostgresTenantAdminRoutingLookup::class,
            $this->app->make(TenantAdminRoutingLookupInterface::class),
        );
        self::assertInstanceOf(
            TenantAdminHostTrustedTenantSelector::class,
            $this->app->make(TrustedTenantSelectorInterface::class),
        );
        self::assertInstanceOf(
            ClinicOwnerTenantContextResolver::class,
            $this->app->make(TenantContextResolverInterface::class),
        );
        self::assertFalse($this->app->bound(PasswordBlocklistInterface::class));
    }

    public function test_application_provider_cannot_initialize_with_invalid_configuration(): void
    {
        $application = new Application(base_path());
        $configuration = new Repository([
            'tenant_management' => [
                'admin_base_domains' => ['app.syifa.my', ' invalid.syifa.my'],
            ],
        ]);
        $application->instance('config', $configuration);
        $application->instance(ConfigRepository::class, $configuration);

        $this->expectException(InvalidTenantAdminDomainConfigurationException::class);
        $this->expectExceptionMessage('Tenant Admin base-domain configuration is invalid.');

        $application->register(TenantManagementServiceProvider::class);
    }

    public function test_provider_enables_local_selection_only_for_local_or_testing_environments(): void
    {
        foreach ([
            'local' => true,
            'testing' => true,
            'staging' => false,
            'production' => false,
        ] as $environment => $expected) {
            $application = new Application(base_path());
            $application->detectEnvironment(static fn (): string => $environment);
            $configuration = new Repository([
                'tenant_management' => [
                    'admin_base_domains' => ['app.syifa.my'],
                    'local_demo_tenant' => [
                        'enabled' => true,
                        'host' => 'localhost',
                        'routing_label' => 'demo-clinic',
                    ],
                ],
            ]);
            $application->instance('config', $configuration);
            $application->instance(ConfigRepository::class, $configuration);
            $application->register(TenantManagementServiceProvider::class);
            $application->instance(
                TenantAdminRoutingLookupInterface::class,
                new class implements TenantAdminRoutingLookupInterface
                {
                    public function resolve(string $normalizedRoutingLabel): ?TenantAdminRoutingData
                    {
                        return $normalizedRoutingLabel === 'demo-clinic'
                            ? new TenantAdminRoutingData(
                                '00000000-0000-4000-8000-000000000001',
                                'demo-clinic',
                                'active',
                            )
                            : null;
                    }
                },
            );

            $selection = $application
                ->make(TrustedTenantSelectorInterface::class)
                ->select('localhost');

            self::assertSame(
                $expected,
                $selection !== null,
                sprintf('Unexpected local tenant selection in %s.', $environment),
            );
        }
    }
}
