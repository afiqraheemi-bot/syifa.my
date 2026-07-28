<?php

declare(strict_types=1);

namespace App\Support\Provisioning;

use App\Modules\ClinicRegistration\Contracts\Review\ClinicRegistrationReviewAuditInterface;
use App\Modules\Onboarding\Contracts\Administration\OnboardingAuditInterface;
use App\Support\Provisioning\Application\ProvisioningWorkflowRepositoryInterface;
use App\Support\Provisioning\Infrastructure\ClinicRegistrationPlatformAuditAdapter;
use App\Support\Provisioning\Infrastructure\OnboardingPlatformAuditAdapter;
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
        $this->app->singleton(OnboardingAuditInterface::class, OnboardingPlatformAuditAdapter::class);
        $this->app->singleton(ClinicRegistrationReviewAuditInterface::class, ClinicRegistrationPlatformAuditAdapter::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/provisioning'));
    }
}
