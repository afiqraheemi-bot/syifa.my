<?php

declare(strict_types=1);

namespace App\Support\Identity;

use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipalResolverInterface;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformAuthorizationInterface;
use App\Modules\TenantManagement\Application\Session\GetCurrentClinicOwnerSessionService;
use App\Modules\TenantManagement\Contracts\Authentication\Signals\ClinicOwnerAuthenticationRejected;
use App\Modules\TenantManagement\Contracts\Authentication\Signals\ClinicOwnerAuthenticationSucceeded;
use App\Modules\TenantManagement\Contracts\Session\ClinicOwnerSessionStoreInterface;
use App\Support\Authorization\Application\AuthenticatedPermissionResolver;
use App\Support\Authorization\Application\AuthenticatedRoleResolver;
use App\Support\Authorization\Application\AuthorizationService;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the identity platform shared across every actor type. Deliberately
 * outside `app/Modules`: this composes Platform Identity's and Clinic
 * Owner's own Contracts into one boundary without either module depending on
 * the other, so it cannot live inside either module's own provider.
 */
final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            CurrentUserResolver::class,
            static fn (Application $application): CurrentUserResolver => new CurrentUserResolver(
                $application->make(AuthFactory::class),
                $application->make(PlatformPrincipalResolverInterface::class),
                $application->make(ClinicOwnerSessionStoreInterface::class),
                $application->make(GetCurrentClinicOwnerSessionService::class),
            ),
        );

        $this->app->bind(CurrentUserInterface::class, static fn (Application $application): CurrentUserResolver => $application->make(CurrentUserResolver::class));
        $this->app->bind(TenantResolverInterface::class, static fn (Application $application): CurrentUserResolver => $application->make(CurrentUserResolver::class));
        $this->app->singleton(AuthenticatedRoleResolver::class);
        $this->app->bind(RoleResolverInterface::class, static fn (Application $application): AuthenticatedRoleResolver => $application->make(AuthenticatedRoleResolver::class));
        $this->app->singleton(
            AuthenticatedPermissionResolver::class,
            static fn (Application $application): AuthenticatedPermissionResolver => new AuthenticatedPermissionResolver(
                $application->make(CurrentUserInterface::class),
                $application->make(PlatformAuthorizationInterface::class),
            ),
        );
        $this->app->bind(PermissionResolverInterface::class, static fn (Application $application): AuthenticatedPermissionResolver => $application->make(AuthenticatedPermissionResolver::class));
        $this->app->singleton(AuthorizationService::class);
    }

    public function boot(): void
    {
        $events = $this->app->make(Dispatcher::class);

        $events->listen(
            ClinicOwnerAuthenticationSucceeded::class,
            [RecordClinicOwnerAuthenticationAuditEntryListener::class, 'handleSucceeded'],
        );
        $events->listen(
            ClinicOwnerAuthenticationRejected::class,
            [RecordClinicOwnerAuthenticationAuditEntryListener::class, 'handleRejected'],
        );
    }
}
