<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Infrastructure;

use App\Modules\TenantManagement\Application\Authentication\AuthenticateClinicOwnerService;
use App\Modules\TenantManagement\Application\Authentication\VerifyClinicOwnerCredentialService;
use App\Modules\TenantManagement\Application\Session\CreateClinicOwnerSessionService;
use App\Modules\TenantManagement\Application\Session\GetCurrentClinicOwnerSessionService;
use App\Modules\TenantManagement\Contracts\Authentication\AuthenticationSignalDispatcherInterface;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationInterface;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerCredentialVerificationInterface;
use App\Modules\TenantManagement\Contracts\Authentication\TrustedTenantSelectorInterface;
use App\Modules\TenantManagement\Contracts\Session\ClinicOwnerSessionStoreInterface;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextResolverInterface;
use App\Modules\TenantManagement\Contracts\TenantOverview\TenantOverviewReadInterface;
use App\Modules\TenantManagement\Contracts\TenantRouting\TenantAdminRoutingLookupInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Repositories\TenantRepositoryInterface;
use App\Modules\TenantManagement\Infrastructure\Authentication\ClinicOwnerUserProvider;
use App\Modules\TenantManagement\Infrastructure\Authentication\LaravelAuthenticationSignalDispatcher;
use App\Modules\TenantManagement\Infrastructure\Persistence\Lookups\PostgresTenantAdminRoutingLookup;
use App\Modules\TenantManagement\Infrastructure\Persistence\Mappers\TenantPersistenceMapper;
use App\Modules\TenantManagement\Infrastructure\Persistence\Queries\PostgresTenantOverviewReadAdapter;
use App\Modules\TenantManagement\Infrastructure\Persistence\Repositories\PostgresTenantRepository;
use App\Modules\TenantManagement\Infrastructure\Session\LaravelClinicOwnerSessionStore;
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
        $this->app->singleton(
            TenantOverviewReadInterface::class,
            static fn (Application $application): PostgresTenantOverviewReadAdapter => new PostgresTenantOverviewReadAdapter(
                $application->make('db')->connection(),
            ),
        );
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

        $this->app->bind(ClinicOwnerAuthenticationInterface::class, AuthenticateClinicOwnerService::class);
        $this->app->bind(AuthenticationSignalDispatcherInterface::class, LaravelAuthenticationSignalDispatcher::class);
        $this->app->bind(ClinicOwnerSessionStoreInterface::class, LaravelClinicOwnerSessionStore::class);

        $this->app->bind(
            CreateClinicOwnerSessionService::class,
            static fn (Application $application): CreateClinicOwnerSessionService => new CreateClinicOwnerSessionService(
                $application->make(ClinicOwnerAuthenticationInterface::class),
                $application->make(AuthenticationSignalDispatcherInterface::class),
                $application->make(ClinicOwnerSessionStoreInterface::class),
                (int) config('tenant_management.session.idle_minutes'),
                (int) config('tenant_management.session.absolute_lifetime_minutes'),
            ),
        );
        $this->app->bind(
            GetCurrentClinicOwnerSessionService::class,
            static fn (Application $application): GetCurrentClinicOwnerSessionService => new GetCurrentClinicOwnerSessionService(
                $application->make(ClinicOwnerSessionStoreInterface::class),
                $application->make(TenantContextResolverInterface::class),
                (int) config('tenant_management.session.idle_minutes'),
            ),
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
                    $application->environment(['local', 'testing'])
                        && self::localDemoTenantEnabled($application),
                    self::localDemoTenantString($application, 'host'),
                    self::localDemoTenantString($application, 'routing_label'),
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

        // Registers the "clinic_owner" auth.providers driver named in
        // config/auth.php. Completes retrieveById/retrieveByToken (session
        // reload, remember-me) for the native Guard — the primary login path
        // never calls Auth::attempt() against it (see
        // ClinicOwnerAuthenticatable's own docblock for why).
        $this->app->make('auth')->provider(
            'clinic_owner',
            fn (Application $application): ClinicOwnerUserProvider => new ClinicOwnerUserProvider(
                $application->make(ClinicOwnerCredentialVerificationInterface::class),
            ),
        );
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

    private static function localDemoTenantString(
        ApplicationContract $application,
        string $key,
    ): string {
        $value = $application
            ->make(ConfigRepository::class)
            ->get('tenant_management.local_demo_tenant.'.$key, '');

        return is_string($value) ? $value : '';
    }

    private static function localDemoTenantEnabled(
        ApplicationContract $application,
    ): bool {
        return $application
            ->make(ConfigRepository::class)
            ->get('tenant_management.local_demo_tenant.enabled') === true;
    }
}
