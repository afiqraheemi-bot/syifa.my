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
use App\Modules\TenantManagement\Contracts\TenantRouting\TenantAdminRoutingLookupInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Repositories\TenantRepositoryInterface;
use App\Modules\TenantManagement\Infrastructure\Authentication\LaravelAuthenticationSignalDispatcher;
use App\Modules\TenantManagement\Infrastructure\Persistence\Lookups\PostgresTenantAdminRoutingLookup;
use App\Modules\TenantManagement\Infrastructure\Persistence\Mappers\TenantPersistenceMapper;
use App\Modules\TenantManagement\Infrastructure\Persistence\Repositories\PostgresTenantRepository;
use App\Modules\TenantManagement\Infrastructure\Session\LaravelClinicOwnerSessionStore;
use App\Modules\TenantManagement\Infrastructure\TenantContext\ClinicOwnerTenantContextResolver;
use App\Modules\TenantManagement\Infrastructure\TenantRouting\AdminHostParser;
use App\Modules\TenantManagement\Infrastructure\TenantRouting\TenantAdminHostTrustedTenantSelector;
use App\Modules\TenantManagement\Presentation\Http\Responses\ProblemDetailsResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application as ApplicationContract;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Response;

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

        RateLimiter::for('clinic-owner-session', static function (Request $request): Limit {
            $email = $request->input('email');
            $email = is_string($email) ? mb_strtolower(trim($email)) : '';
            $network = self::coarseNetworkSignal($request->ip());
            $key = hash('sha256', strtolower($request->getHost()).'|'.hash('sha256', $email).'|'.$network);

            return Limit::perMinute((int) config('tenant_management.session.login_attempts_per_minute'))
                ->by($key)
                ->response(static fn (Request $limitedRequest, array $headers): Response => ProblemDetailsResponse::make(
                    $limitedRequest,
                    'authentication_temporarily_unavailable',
                    'Authentication Temporarily Unavailable',
                    429,
                    'Authentication is temporarily unavailable. Please try again later.',
                )->withHeaders($headers));
        });
    }

    private static function coarseNetworkSignal(?string $ip): string
    {
        if ($ip === null || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return 'unavailable';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $parts = explode('.', $ip);

            return implode('.', array_slice($parts, 0, 3)).'.0/24';
        }

        $packed = inet_pton($ip);

        return $packed === false ? 'unavailable' : bin2hex(substr($packed, 0, 8)).'/64';
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
