<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Infrastructure;

use App\Modules\Onboarding\Application\Provisioning\ProvisionOnboardingJobService;
use App\Modules\Onboarding\Contracts\Administration\PendingOnboardingJobsReadInterface;
use App\Modules\Onboarding\Contracts\Administration\SuperAdminOnboardingReadInterface;
use App\Modules\Onboarding\Contracts\Administration\WebsiteDesignerEligibilityInterface;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerDashboardReadInterface;
use App\Modules\Onboarding\Contracts\LaunchReadiness\LaunchReadinessReadInterface;
use App\Modules\Onboarding\Contracts\Provisioning\ProvisionOnboardingJobInterface;
use App\Modules\Onboarding\Contracts\Tasks\OnboardingTaskReadInterface;
use App\Modules\Onboarding\Contracts\WebsiteApproval\ClinicOwnerWebsiteApprovalReadInterface;
use App\Modules\Onboarding\Contracts\WebsiteApproval\OnboardingWorkflowTransactionInterface;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Repositories\OnboardingJobRepositoryInterface;
use App\Modules\Onboarding\Infrastructure\Persistence\Mappers\OnboardingJobPersistenceMapper;
use App\Modules\Onboarding\Infrastructure\Persistence\OnboardingDatabaseWorkflowTransaction;
use App\Modules\Onboarding\Infrastructure\Persistence\Repositories\PostgresOnboardingJobRepository;
use App\Modules\Onboarding\Infrastructure\Queries\PostgresClinicOwnerWebsiteApprovalReadAdapter;
use App\Modules\Onboarding\Infrastructure\Queries\PostgresLaunchReadinessReadAdapter;
use App\Modules\Onboarding\Infrastructure\Queries\PostgresOnboardingTaskReadAdapter;
use App\Modules\Onboarding\Infrastructure\Queries\PostgresSuperAdminOnboardingAdapter;
use App\Modules\Onboarding\Infrastructure\Queries\PostgresWebsiteDesignerDashboardReadAdapter;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class OnboardingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProvisionOnboardingJobInterface::class, ProvisionOnboardingJobService::class);
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
            OnboardingWorkflowTransactionInterface::class,
            static fn (Application $application): OnboardingDatabaseWorkflowTransaction => new OnboardingDatabaseWorkflowTransaction(
                $application->make('db')->connection(),
            ),
        );
        $this->app->singleton(
            ClinicOwnerWebsiteApprovalReadInterface::class,
            static fn (Application $application): PostgresClinicOwnerWebsiteApprovalReadAdapter => new PostgresClinicOwnerWebsiteApprovalReadAdapter(
                $application->make('db')->connection(),
            ),
        );
        $this->app->singleton(
            WebsiteDesignerDashboardReadInterface::class,
            static fn (Application $application): PostgresWebsiteDesignerDashboardReadAdapter => new PostgresWebsiteDesignerDashboardReadAdapter(
                $application->make('db')->connection(),
            ),
        );
        $this->app->singleton(
            OnboardingTaskReadInterface::class,
            static fn (Application $application): PostgresOnboardingTaskReadAdapter => new PostgresOnboardingTaskReadAdapter(
                $application->make('db')->connection(),
            ),
        );
        $this->app->singleton(
            LaunchReadinessReadInterface::class,
            static fn (Application $application): PostgresLaunchReadinessReadAdapter => new PostgresLaunchReadinessReadAdapter(
                $application->make('db')->connection(),
            ),
        );
        $this->app->singleton(
            PostgresSuperAdminOnboardingAdapter::class,
            static fn (Application $application): PostgresSuperAdminOnboardingAdapter => new PostgresSuperAdminOnboardingAdapter(
                $application->make('db')->connection(),
            ),
        );
        $this->app->alias(PostgresSuperAdminOnboardingAdapter::class, SuperAdminOnboardingReadInterface::class);
        $this->app->alias(PostgresSuperAdminOnboardingAdapter::class, PendingOnboardingJobsReadInterface::class);
        $this->app->alias(PostgresSuperAdminOnboardingAdapter::class, WebsiteDesignerEligibilityInterface::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/onboarding'));
    }
}
