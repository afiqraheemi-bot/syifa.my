<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Infrastructure;

use App\Modules\PlatformAdministration\Application\AuditEntry\RecordAuditEntryService;
use App\Modules\PlatformAdministration\Application\Authentication\AuthenticatePlatformSessionService;
use App\Modules\PlatformAdministration\Application\Authentication\LogoutPlatformSessionService;
use App\Modules\PlatformAdministration\Application\Authentication\PlatformPrincipalResolver;
use App\Modules\PlatformAdministration\Application\Authorization\AuthorizePlatformActionService;
use App\Modules\PlatformAdministration\Application\EmailVerification\SendPlatformEmailVerificationNotificationService;
use App\Modules\PlatformAdministration\Application\EmailVerification\VerifyPlatformEmailService;
use App\Modules\PlatformAdministration\Application\PasswordConfirmation\ConfirmPlatformPasswordService;
use App\Modules\PlatformAdministration\Application\PasswordReset\RequestPlatformPasswordResetService;
use App\Modules\PlatformAdministration\Application\PasswordReset\ResetPlatformPasswordService;
use App\Modules\PlatformAdministration\Application\PlatformIdentity\GetPlatformIdentityService;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditCorrelationIdResolverInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRepositoryInterface;
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
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\PlatformWorkforceCredentialPasswordWriterInterface;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\PlatformWorkforceCredentialStateWriterInterface;
use App\Modules\PlatformAdministration\Domain\Authorization\PlatformAuthorizationService;
use App\Modules\PlatformAdministration\Infrastructure\Authentication\PlatformIdentityUserProvider;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\AuditEntry\Mappers\AuditEntryPersistenceMapper;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\AuditEntry\PostgresAuditEntryRepository;
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
use App\Modules\PlatformAdministration\Infrastructure\Support\RequestAuditCorrelationIdResolver;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\PasswordBrokerFactory;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

final class PlatformAdministrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuditEntryPersistenceMapper::class);

        $this->app->singleton(
            PostgresAuditEntryRepository::class,
            static function (Application $application): PostgresAuditEntryRepository {
                return new PostgresAuditEntryRepository(
                    $application->make('db')->connection(),
                    $application->make(AuditEntryPersistenceMapper::class),
                );
            },
        );
        $this->app->alias(PostgresAuditEntryRepository::class, AuditEntryRepositoryInterface::class);

        $this->app->singleton(
            RecordAuditEntryService::class,
            static fn (Application $application): RecordAuditEntryService => new RecordAuditEntryService(
                $application->make(AuditEntryRepositoryInterface::class),
            ),
        );
        $this->app->alias(RecordAuditEntryService::class, AuditEntryRecorderInterface::class);

        $this->app->singleton(RequestAuditCorrelationIdResolver::class);
        $this->app->alias(
            RequestAuditCorrelationIdResolver::class,
            AuditCorrelationIdResolverInterface::class,
        );

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
        $this->app->alias(
            PostgresPlatformWorkforceCredentialAdapter::class,
            PlatformWorkforceCredentialPasswordWriterInterface::class,
        );
        $this->app->alias(
            PostgresPlatformWorkforceCredentialAdapter::class,
            PlatformWorkforceCredentialStateWriterInterface::class,
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
                $application->make(AuditEntryRecorderInterface::class),
                $application->make(AuditCorrelationIdResolverInterface::class),
                $application->make(LoggerInterface::class),
            ),
        );
        $this->app->alias(AuthorizePlatformActionService::class, PlatformAuthorizationInterface::class);

        $this->app->singleton(
            LaravelPlatformSessionStore::class,
            static function (Application $application): LaravelPlatformSessionStore {
                return new LaravelPlatformSessionStore(
                    $application->make('session.store'),
                    $application->make(AuthFactory::class),
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
                $application->make(AuthFactory::class),
                $application->make(PlatformWorkforceCredentialLookupInterface::class),
                $application->make(PlatformIdentityLookupInterface::class),
                $application->make(PlatformSessionStoreInterface::class),
                $application->make(AuditEntryRecorderInterface::class),
                $application->make(AuditCorrelationIdResolverInterface::class),
                $application->make(LoggerInterface::class),
            ),
        );
        $this->app->alias(
            AuthenticatePlatformSessionService::class,
            PlatformSessionAuthenticationInterface::class,
        );

        $this->app->singleton(
            LogoutPlatformSessionService::class,
            static fn (Application $application): LogoutPlatformSessionService => new LogoutPlatformSessionService(
                $application->make(PlatformPrincipalResolverInterface::class),
                $application->make(PlatformSessionStoreInterface::class),
                $application->make(AuditEntryRecorderInterface::class),
                $application->make(AuditCorrelationIdResolverInterface::class),
                $application->make(LoggerInterface::class),
            ),
        );

        $this->app->singleton(
            RequestPlatformPasswordResetService::class,
            static fn (Application $application): RequestPlatformPasswordResetService => new RequestPlatformPasswordResetService(
                $application->make(PasswordBrokerFactory::class),
                $application->make(PlatformWorkforceCredentialLookupInterface::class),
                $application->make(AuditEntryRecorderInterface::class),
                $application->make(AuditCorrelationIdResolverInterface::class),
                $application->make(LoggerInterface::class),
            ),
        );

        $this->app->singleton(
            ResetPlatformPasswordService::class,
            static fn (Application $application): ResetPlatformPasswordService => new ResetPlatformPasswordService(
                $application->make(PasswordBrokerFactory::class),
                $application->make(PlatformWorkforceCredentialPasswordWriterInterface::class),
                $application->make(AuditEntryRecorderInterface::class),
                $application->make(AuditCorrelationIdResolverInterface::class),
                $application->make(LoggerInterface::class),
            ),
        );

        $this->app->singleton(
            VerifyPlatformEmailService::class,
            static fn (Application $application): VerifyPlatformEmailService => new VerifyPlatformEmailService(
                $application->make(PlatformIdentityLookupInterface::class),
                $application->make(PlatformWorkforceCredentialLookupInterface::class),
                $application->make(PlatformWorkforceCredentialStateWriterInterface::class),
                $application->make(AuditEntryRecorderInterface::class),
                $application->make(AuditCorrelationIdResolverInterface::class),
                $application->make(LoggerInterface::class),
            ),
        );

        $this->app->singleton(
            SendPlatformEmailVerificationNotificationService::class,
            static fn (Application $application): SendPlatformEmailVerificationNotificationService => new SendPlatformEmailVerificationNotificationService(
                $application->make(AuthFactory::class),
                $application->make(AuditEntryRecorderInterface::class),
                $application->make(AuditCorrelationIdResolverInterface::class),
                $application->make(LoggerInterface::class),
            ),
        );

        $this->app->singleton(
            ConfirmPlatformPasswordService::class,
            static fn (Application $application): ConfirmPlatformPasswordService => new ConfirmPlatformPasswordService(
                $application->make(AuthFactory::class),
                $application->make('session.store'),
                $application->make(PlatformIdentityLookupInterface::class),
                $application->make(AuditEntryRecorderInterface::class),
                $application->make(AuditCorrelationIdResolverInterface::class),
                $application->make(LoggerInterface::class),
            ),
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/platform_administration'));

        // Registers the "platform_identity" auth.providers driver named in
        // config/auth.php — the sole bridge between Laravel's native Guard and
        // this module's existing, already-hardened CredentialVerificationInterface.
        $this->app->make('auth')->provider(
            'platform_identity',
            fn (Application $application): PlatformIdentityUserProvider => new PlatformIdentityUserProvider(
                $application->make(PlatformWorkforceCredentialLookupInterface::class),
                $application->make(CredentialVerificationInterface::class),
                $application->make(Hasher::class),
            ),
        );

        // Laravel's native VerifyEmail notification hardcodes the
        // "verification.verify" route name unless told otherwise — this
        // module names its route "platform.email.verify" instead, so every
        // actor type can eventually have its own without a name collision.
        VerifyEmail::createUrlUsing(static function (AuthenticatableContract&MustVerifyEmail $notifiable): string {
            return URL::temporarySignedRoute(
                'platform.email.verify',
                now()->addMinutes((int) config('auth.verification.expire', 60)),
                ['id' => $notifiable->getAuthIdentifier(), 'hash' => sha1($notifiable->getEmailForVerification())],
            );
        });
    }
}
