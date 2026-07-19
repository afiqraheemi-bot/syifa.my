<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Infrastructure;

use App\Modules\PlatformAdministration\Application\Authentication\AuthenticatePlatformSessionService;
use App\Modules\PlatformAdministration\Application\Authentication\LogoutPlatformSessionService;
use App\Modules\PlatformAdministration\Application\Authentication\PlatformPrincipalResolver;
use App\Modules\PlatformAdministration\Application\Authorization\AuthorizePlatformActionService;
use App\Modules\PlatformAdministration\Application\PlatformIdentity\GetPlatformIdentityService;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipalResolverInterface;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformSessionAuthenticationInterface;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformSessionStoreInterface;
use App\Modules\PlatformAdministration\Contracts\Authorization\CategoryGrantLookupInterface;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformAdministratorLookupInterface;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformAuthorizationInterface;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformCategoryLookupInterface;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformPermissionLookupInterface;
use App\Modules\PlatformAdministration\Contracts\PlatformIdentity\PlatformIdentityLookupInterface;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\CredentialVerificationInterface;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\PlatformWorkforceCredentialLookupInterface;
use App\Modules\PlatformAdministration\Domain\Authorization\PlatformAuthorizationService;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\Authorization\Mappers\PlatformAuthorizationPersistenceMapper;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\Authorization\PostgresCategoryGrantLookup;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\Authorization\PostgresPlatformAdministratorLookup;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\Authorization\PostgresPlatformCategoryLookup;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\Authorization\PostgresPlatformPermissionLookup;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\PlatformIdentity\Mappers\PlatformIdentityPersistenceMapper;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\PlatformIdentity\PostgresPlatformIdentityLookup;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\WorkforceCredentials\Mappers\PlatformWorkforceCredentialPersistenceMapper;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\WorkforceCredentials\PostgresPlatformWorkforceCredentialAdapter;
use App\Modules\PlatformAdministration\Infrastructure\Session\LaravelPlatformSessionStore;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class PlatformAdministrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            PostgresPlatformWorkforceCredentialAdapter::class,
            static function (Application $application): PostgresPlatformWorkforceCredentialAdapter {
                $database = $application->make('db');

                return new PostgresPlatformWorkforceCredentialAdapter(
                    $database->connection(),
                    new PlatformWorkforceCredentialPersistenceMapper,
                    $application->make(Hasher::class),
                );
            },
        );
        $this->app->alias(
            PostgresPlatformWorkforceCredentialAdapter::class,
            PlatformWorkforceCredentialLookupInterface::class,
        );
        $this->app->alias(
            PostgresPlatformWorkforceCredentialAdapter::class,
            CredentialVerificationInterface::class,
        );

        $this->app->singleton(
            PostgresPlatformIdentityLookup::class,
            static function (Application $application): PostgresPlatformIdentityLookup {
                $database = $application->make('db');

                return new PostgresPlatformIdentityLookup(
                    $database->connection(),
                    new PlatformIdentityPersistenceMapper,
                );
            },
        );
        $this->app->alias(PostgresPlatformIdentityLookup::class, PlatformIdentityLookupInterface::class);

        $this->app->singleton(PlatformAuthorizationPersistenceMapper::class);

        $this->app->singleton(
            PostgresPlatformAdministratorLookup::class,
            static fn (Application $application): PostgresPlatformAdministratorLookup => new PostgresPlatformAdministratorLookup(
                $application->make('db')->connection(),
                $application->make(PlatformAuthorizationPersistenceMapper::class),
            ),
        );
        $this->app->alias(PostgresPlatformAdministratorLookup::class, PlatformAdministratorLookupInterface::class);

        $this->app->singleton(
            PostgresPlatformCategoryLookup::class,
            static fn (Application $application): PostgresPlatformCategoryLookup => new PostgresPlatformCategoryLookup(
                $application->make('db')->connection(),
                $application->make(PlatformAuthorizationPersistenceMapper::class),
            ),
        );
        $this->app->alias(PostgresPlatformCategoryLookup::class, PlatformCategoryLookupInterface::class);

        $this->app->singleton(
            PostgresPlatformPermissionLookup::class,
            static fn (Application $application): PostgresPlatformPermissionLookup => new PostgresPlatformPermissionLookup(
                $application->make('db')->connection(),
                $application->make(PlatformAuthorizationPersistenceMapper::class),
            ),
        );
        $this->app->alias(PostgresPlatformPermissionLookup::class, PlatformPermissionLookupInterface::class);

        $this->app->singleton(
            PostgresCategoryGrantLookup::class,
            static fn (Application $application): PostgresCategoryGrantLookup => new PostgresCategoryGrantLookup(
                $application->make('db')->connection(),
                $application->make(PlatformAuthorizationPersistenceMapper::class),
            ),
        );
        $this->app->alias(PostgresCategoryGrantLookup::class, CategoryGrantLookupInterface::class);

        $this->app->singleton(
            AuthorizePlatformActionService::class,
            static fn (Application $application): AuthorizePlatformActionService => new AuthorizePlatformActionService(
                $application->make(GetPlatformIdentityService::class),
                $application->make(PlatformAdministratorLookupInterface::class),
                $application->make(PlatformCategoryLookupInterface::class),
                $application->make(PlatformPermissionLookupInterface::class),
                $application->make(CategoryGrantLookupInterface::class),
                $application->make(PlatformAuthorizationService::class),
            ),
        );
        $this->app->alias(AuthorizePlatformActionService::class, PlatformAuthorizationInterface::class);

        $this->app->singleton(
            LaravelPlatformSessionStore::class,
            static function (Application $application): LaravelPlatformSessionStore {
                return new LaravelPlatformSessionStore(
                    $application->make('session.store'),
                    (int) config('platform_administration.session.idle_minutes'),
                    (int) config('platform_administration.session.absolute_lifetime_minutes'),
                );
            },
        );
        $this->app->alias(LaravelPlatformSessionStore::class, PlatformSessionStoreInterface::class);

        $this->app->singleton(
            PlatformPrincipalResolver::class,
            static fn (Application $application): PlatformPrincipalResolver => new PlatformPrincipalResolver(
                $application->make(PlatformSessionStoreInterface::class),
                $application->make(PlatformIdentityLookupInterface::class),
            ),
        );
        $this->app->alias(PlatformPrincipalResolver::class, PlatformPrincipalResolverInterface::class);

        $this->app->singleton(
            AuthenticatePlatformSessionService::class,
            static fn (Application $application): AuthenticatePlatformSessionService => new AuthenticatePlatformSessionService(
                $application->make(CredentialVerificationInterface::class),
                $application->make(PlatformIdentityLookupInterface::class),
                $application->make(PlatformSessionStoreInterface::class),
            ),
        );
        $this->app->alias(
            AuthenticatePlatformSessionService::class,
            PlatformSessionAuthenticationInterface::class,
        );

        $this->app->singleton(
            LogoutPlatformSessionService::class,
            static fn (Application $application): LogoutPlatformSessionService => new LogoutPlatformSessionService(
                $application->make(PlatformSessionStoreInterface::class),
            ),
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/platform_administration'));
    }
}
