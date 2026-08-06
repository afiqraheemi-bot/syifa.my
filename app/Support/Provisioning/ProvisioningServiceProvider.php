<?php

declare(strict_types=1);

namespace App\Support\Provisioning;

use App\Modules\Booking\Contracts\ServiceSetup\ServiceSetupAuditInterface;
use App\Modules\ClinicRegistration\Application\ViewCurrentClinicRegistrationService;
use App\Modules\ClinicRegistration\Contracts\Authentication\ClinicRegistrationAccessInterface;
use App\Modules\ClinicRegistration\Contracts\Authentication\ClinicRegistrationLoginInterface;
use App\Modules\ClinicRegistration\Contracts\Checkout\CompleteLocalDemoAcquisitionInterface;
use App\Modules\ClinicRegistration\Contracts\Review\ClinicRegistrationReviewAuditInterface;
use App\Modules\ClinicRegistration\Contracts\Tracking\RegistrationTrackingCredentialWriterInterface;
use App\Modules\Onboarding\Contracts\Administration\OnboardingAuditInterface;
use App\Modules\Onboarding\Contracts\WebsiteApproval\WebsiteApprovalAuditInterface;
use App\Modules\SubscriptionBilling\Application\Subscription\ActivateSubscriptionFromVerifiedPaymentService;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\SubscriptionEntitlementComputationInterface;
use App\Modules\TenantManagement\Application\Administration\ActivateSelfRegisteredClinicOwnerService;
use App\Modules\TenantManagement\Contracts\Session\ClinicOwnerSessionStoreInterface;
use App\Modules\WebsiteBuilder\Contracts\Publication\WebsitePublicationApprovalReadInterface;
use App\Support\Provisioning\Application\ClinicRegistrationLoginService;
use App\Support\Provisioning\Application\CompleteLocalDemoAcquisitionService;
use App\Support\Provisioning\Application\LocalDemoSubscriptionEntitlementComputation;
use App\Support\Provisioning\Application\ProvisioningWorkflowRepositoryInterface;
use App\Support\Provisioning\Infrastructure\BookingServiceSetupPlatformAuditAdapter;
use App\Support\Provisioning\Infrastructure\ClinicRegistrationPlatformAuditAdapter;
use App\Support\Provisioning\Infrastructure\OnboardingPlatformAuditAdapter;
use App\Support\Provisioning\Infrastructure\OnboardingWebsitePublicationApprovalAdapter;
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
        $this->app->singleton(
            CompleteLocalDemoAcquisitionInterface::class,
            CompleteLocalDemoAcquisitionService::class,
        );
        $this->app->singleton(
            ClinicRegistrationLoginInterface::class,
            static fn (Application $application): ClinicRegistrationLoginService => new ClinicRegistrationLoginService(
                $application->make(ClinicRegistrationAccessInterface::class),
                $application->make(RegistrationTrackingCredentialWriterInterface::class),
                $application->make(ViewCurrentClinicRegistrationService::class),
                $application->make(ActivateSelfRegisteredClinicOwnerService::class),
                $application->make(ClinicOwnerSessionStoreInterface::class),
                (int) config('tenant_management.session.absolute_lifetime_minutes'),
            ),
        );
        if ($this->app->environment(['local', 'testing'])) {
            $this->app->when(ActivateSubscriptionFromVerifiedPaymentService::class)
                ->needs(SubscriptionEntitlementComputationInterface::class)
                ->give(LocalDemoSubscriptionEntitlementComputation::class);
        }
        $this->app->singleton(OnboardingAuditInterface::class, OnboardingPlatformAuditAdapter::class);
        $this->app->alias(OnboardingAuditInterface::class, WebsiteApprovalAuditInterface::class);
        $this->app->singleton(
            WebsitePublicationApprovalReadInterface::class,
            OnboardingWebsitePublicationApprovalAdapter::class,
        );
        $this->app->singleton(ClinicRegistrationReviewAuditInterface::class, ClinicRegistrationPlatformAuditAdapter::class);
        $this->app->singleton(ServiceSetupAuditInterface::class, BookingServiceSetupPlatformAuditAdapter::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/provisioning'));
    }
}
