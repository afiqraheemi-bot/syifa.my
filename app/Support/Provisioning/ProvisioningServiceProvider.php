<?php

declare(strict_types=1);

namespace App\Support\Provisioning;

use App\Support\Provisioning\Application\ProvisioningWorkflowRepositoryInterface;
use App\Support\Provisioning\Infrastructure\PostgresProvisioningWorkflowRepository;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class ProvisioningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            ProvisioningWorkflowRepositoryInterface::class,
            static fn (Application $application): PostgresProvisioningWorkflowRepository => new PostgresProvisioningWorkflowRepository(
                $application->make('db')->connection(),
            ),
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/provisioning'));
    }
}
