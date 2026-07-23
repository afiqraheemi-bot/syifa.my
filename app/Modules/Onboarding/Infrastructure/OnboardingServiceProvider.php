<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Infrastructure;

use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerDashboardReadInterface;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Repositories\OnboardingJobRepositoryInterface;
use App\Modules\Onboarding\Infrastructure\Persistence\Mappers\OnboardingJobPersistenceMapper;
use App\Modules\Onboarding\Infrastructure\Persistence\Repositories\PostgresOnboardingJobRepository;
use App\Modules\Onboarding\Infrastructure\Queries\PostgresWebsiteDesignerDashboardReadAdapter;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class OnboardingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            OnboardingJobRepositoryInterface::class,
            static function (Application $application): PostgresOnboardingJobRepository {
                $database = $application->make('db');

                return new PostgresOnboardingJobRepository(
                    $database->connection(),
                    new OnboardingJobPersistenceMapper,
                );
            },
        );
        $this->app->singleton(
            WebsiteDesignerDashboardReadInterface::class,
            static fn (Application $application): PostgresWebsiteDesignerDashboardReadAdapter => new PostgresWebsiteDesignerDashboardReadAdapter(
                $application->make('db')->connection(),
            ),
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/onboarding'));
    }
}
