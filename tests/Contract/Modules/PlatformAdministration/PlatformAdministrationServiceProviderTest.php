<?php

declare(strict_types=1);

namespace Tests\Contract\Modules\PlatformAdministration;

use App\Modules\PlatformAdministration\Application\AuditEntry\RecordAuditEntryService;
use App\Modules\PlatformAdministration\Application\Authentication\AuthenticatePlatformSessionService;
use App\Modules\PlatformAdministration\Application\Authentication\LogoutPlatformSessionService;
use App\Modules\PlatformAdministration\Application\Authentication\PlatformPrincipalResolver;
use App\Modules\PlatformAdministration\Application\Authorization\AuthorizePlatformActionService;
use App\Modules\PlatformAdministration\Application\PlatformIdentity\GetPlatformIdentityService;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditCorrelationIdResolverInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRepositoryInterface;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipalResolverInterface;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformSessionAuthenticationInterface;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformSessionStoreInterface;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformAuthorizationInterface;
use App\Modules\PlatformAdministration\Contracts\PlatformIdentity\PlatformIdentityLookupInterface;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\CredentialVerificationInterface;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\PlatformWorkforceCredentialLookupInterface;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\AuditEntry\PostgresAuditEntryRepository;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\PlatformIdentity\PostgresPlatformIdentityLookup;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\WorkforceCredentials\PostgresPlatformWorkforceCredentialAdapter;
use App\Modules\PlatformAdministration\Infrastructure\PlatformAdministrationServiceProvider;
use App\Modules\PlatformAdministration\Infrastructure\Session\LaravelPlatformSessionStore;
use App\Modules\PlatformAdministration\Infrastructure\Support\RequestAuditCorrelationIdResolver;
use App\Modules\TenantManagement\Application\Authentication\ChangeClinicOwnerPasswordService;
use App\Modules\TenantManagement\Contracts\Authentication\PasswordBlocklistInterface;
use App\Modules\TenantManagement\Contracts\Authentication\TrustedTenantSelectorInterface;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextResolverInterface;
use App\Modules\TenantManagement\Infrastructure\TenantContext\ClinicOwnerTenantContextResolver;
use App\Modules\TenantManagement\Infrastructure\TenantRouting\TenantAdminHostTrustedTenantSelector;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Migrations\Migrator;
use Tests\TestCase;

final class PlatformAdministrationServiceProviderTest extends TestCase
{
    public function test_provider_is_registered_and_loads_its_migration_path(): void
    {
        self::assertArrayHasKey(
            PlatformAdministrationServiceProvider::class,
            $this->app->getLoadedProviders(),
        );
        $migrator = $this->app->make('migrator');
        self::assertInstanceOf(Migrator::class, $migrator);
        self::assertContains(
            database_path('migrations/platform_administration'),
            $migrator->paths(),
        );
    }

    public function test_approved_credential_contracts_resolve_to_the_same_postgres_adapter(): void
    {
        $lookup = $this->app->make(PlatformWorkforceCredentialLookupInterface::class);
        $verification = $this->app->make(CredentialVerificationInterface::class);

        self::assertInstanceOf(PostgresPlatformWorkforceCredentialAdapter::class, $lookup);
        self::assertSame($lookup, $verification);
    }

    public function test_platform_identity_lookup_and_platform_session_bindings_resolve(): void
    {
        self::assertInstanceOf(
            PostgresAuditEntryRepository::class,
            $this->app->make(AuditEntryRepositoryInterface::class),
        );
        self::assertInstanceOf(
            RecordAuditEntryService::class,
            $this->app->make(AuditEntryRecorderInterface::class),
        );
        self::assertInstanceOf(
            RequestAuditCorrelationIdResolver::class,
            $this->app->make(AuditCorrelationIdResolverInterface::class),
        );
        self::assertInstanceOf(
            PostgresPlatformIdentityLookup::class,
            $this->app->make(PlatformIdentityLookupInterface::class),
        );
        self::assertInstanceOf(
            GetPlatformIdentityService::class,
            $this->app->make(GetPlatformIdentityService::class),
        );
        self::assertInstanceOf(
            LaravelPlatformSessionStore::class,
            $this->app->make(PlatformSessionStoreInterface::class),
        );
        self::assertInstanceOf(
            PlatformPrincipalResolver::class,
            $this->app->make(PlatformPrincipalResolverInterface::class),
        );
        self::assertInstanceOf(
            AuthenticatePlatformSessionService::class,
            $this->app->make(PlatformSessionAuthenticationInterface::class),
        );
        self::assertInstanceOf(
            LogoutPlatformSessionService::class,
            $this->app->make(LogoutPlatformSessionService::class),
        );
        self::assertInstanceOf(
            AuthorizePlatformActionService::class,
            $this->app->make(PlatformAuthorizationInterface::class),
        );
    }

    public function test_security_contracts_without_production_adapters_remain_unresolved(): void
    {
        foreach ([PasswordBlocklistInterface::class] as $contract) {
            self::assertFalse($this->app->bound($contract), $contract);

            try {
                $this->app->make($contract);
                self::fail($contract.' must remain unresolved.');
            } catch (BindingResolutionException) {
                self::addToAssertionCount(1);
            }
        }

        self::assertInstanceOf(
            TenantAdminHostTrustedTenantSelector::class,
            $this->app->make(TrustedTenantSelectorInterface::class),
        );
        self::assertInstanceOf(
            ClinicOwnerTenantContextResolver::class,
            $this->app->make(TenantContextResolverInterface::class),
        );
    }

    public function test_password_change_remains_unresolvable_without_a_real_blocklist(): void
    {
        $this->expectException(BindingResolutionException::class);
        $this->app->make(ChangeClinicOwnerPasswordService::class);
    }
}
