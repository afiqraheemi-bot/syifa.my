<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Infrastructure;

use App\Modules\TenantManagement\Application\Authentication\VerifyClinicOwnerCredentialService;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerCredentialVerificationInterface;
use App\Modules\TenantManagement\Contracts\TenantRouting\TenantAdminRoutingLookupInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Repositories\TenantRepositoryInterface;
use App\Modules\TenantManagement\Infrastructure\Persistence\Lookups\PostgresTenantAdminRoutingLookup;
use App\Modules\TenantManagement\Infrastructure\Persistence\Mappers\TenantPersistenceMapper;
use App\Modules\TenantManagement\Infrastructure\Persistence\Repositories\PostgresTenantRepository;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class TenantManagementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
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
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/tenant_management'));
    }
}
