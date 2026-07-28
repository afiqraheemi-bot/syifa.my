<?php

declare(strict_types=1);

use App\Modules\Booking\Infrastructure\BookingServiceProvider;
use App\Modules\ClinicRegistration\Infrastructure\ClinicRegistrationServiceProvider;
use App\Modules\Commercial\Infrastructure\CommercialServiceProvider;
use App\Modules\Notification\Infrastructure\NotificationServiceProvider;
use App\Modules\Onboarding\Infrastructure\OnboardingServiceProvider;
use App\Modules\PlatformAdministration\Infrastructure\PlatformAdministrationServiceProvider;
use App\Modules\ReportingAnalytics\Infrastructure\ReportingAnalyticsServiceProvider;
use App\Modules\SubscriptionBilling\Infrastructure\SubscriptionBillingServiceProvider;
use App\Modules\TenantManagement\Infrastructure\TenantManagementServiceProvider;
use App\Modules\WebsiteBuilder\Infrastructure\WebsiteBuilderServiceProvider;
use App\Providers\AppServiceProvider;
use App\Support\Identity\IdentityServiceProvider;
use App\Support\Provisioning\ProvisioningServiceProvider;

return [
    AppServiceProvider::class,
    TenantManagementServiceProvider::class,
    WebsiteBuilderServiceProvider::class,
    OnboardingServiceProvider::class,
    NotificationServiceProvider::class,
    ClinicRegistrationServiceProvider::class,
    CommercialServiceProvider::class,
    PlatformAdministrationServiceProvider::class,
    ReportingAnalyticsServiceProvider::class,
    SubscriptionBillingServiceProvider::class,
    BookingServiceProvider::class,
    IdentityServiceProvider::class,
    ProvisioningServiceProvider::class,
];
