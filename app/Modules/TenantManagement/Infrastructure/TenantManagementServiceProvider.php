<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Infrastructure;

use App\Modules\TenantManagement\Application\Authentication\VerifyClinicOwnerCredentialService;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerCredentialVerificationInterface;
use App\Modules\TenantManagement\Contracts\Authentication\TrustedTenantSelectorInterface;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextResolverInterface;
use App\Modules\TenantManagement\Contracts\TenantRouting\TenantAdminRoutingLookupInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Repositories\TenantRepositoryInterface;
use App\Modules\TenantManagement\Infrastructure\Persistence\Lookups\PostgresTenantAdminRoutingLookup;
use App\Modules\TenantManagement\Infrastructure\Persistence\Mappers\TenantPersistenceMapper;
use App\Modules\TenantManagement\Infrastructure\Persistence\Repositories\PostgresTenantRepository;
use App\Modules\TenantManagement\Infrastructure\TenantContext\ClinicOwnerTenantContextResolver;
use App\Modules\TenantManagement\Infrastructure\TenantRouting\AdminHostParser;
use App\Modules\TenantManagement\Infrastructure\TenantRouting\TenantAdminHostTrustedTenantSelector;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application as ApplicationContract;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class TenantManagementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->instance(
            AdminHostParser::class,
            new AdminHostParser(self::adminBaseDomains($this->app)),
        );

        $this->app->singleton(
            TenantRepositoryInterface::class,
            static function (Application $application): PostgresTenantRepository {
                $database = $application->make('db');

                return new PostgresTenantRepository(
                    $database->connection(),
                    new TenantPersistenceMapper,
                );
            },
        );

        $this->app->bind(
            ClinicOwnerCredentialVerificationInterface::class,
            VerifyClinicOwnerCredentialService::class,
        );

        $this->app->singleton(
            TenantAdminRoutingLookupInterface::class,
            static function (Application $application): PostgresTenantAdminRoutingLookup {
                $database = $application->make('db');

                return new PostgresTenantAdminRoutingLookup($database->connection());
            },
        );

        $this->app->singleton(
            TrustedTenantSelectorInterface::class,
            static function (Application $application): TenantAdminHostTrustedTenantSelector {
                return new TenantAdminHostTrustedTenantSelector(
                    $application->make(AdminHostParser::class),
                    $application->make(TenantAdminRoutingLookupInterface::class),
                );
            },
        );

        $this->app->singleton(
            TenantContextResolverInterface::class,
            static fn (Application $application): ClinicOwnerTenantContextResolver => new ClinicOwnerTenantContextResolver(
                $application->make(TenantRepositoryInterface::class),
            ),
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/tenant_management'));
    }

    /** @return list<string> */
    private static function adminBaseDomains(ApplicationContract $application): array
    {
        $configuredDomains = $application
            ->make(ConfigRepository::class)
            ->get('tenant_management.admin_base_domains', []);

        if (! is_array($configuredDomains) || ! array_is_list($configuredDomains)) {
            return [];
        }

        $adminBaseDomains = [];

        foreach ($configuredDomains as $configuredDomain) {
            if (! is_string($configuredDomain)) {
                return [];
            }

            $adminBaseDomains[] = $configuredDomain;
        }

        return $adminBaseDomains;
    }
}
