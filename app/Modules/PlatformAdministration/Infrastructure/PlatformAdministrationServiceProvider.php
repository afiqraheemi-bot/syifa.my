<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Infrastructure;

use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\CredentialVerificationInterface;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\PlatformWorkforceCredentialLookupInterface;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\WorkforceCredentials\Mappers\PlatformWorkforceCredentialPersistenceMapper;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\WorkforceCredentials\PostgresPlatformWorkforceCredentialAdapter;
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
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/platform_administration'));
    }
}
