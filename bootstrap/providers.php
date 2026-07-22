<?php

declare(strict_types=1);

use App\Modules\Booking\Infrastructure\BookingServiceProvider;
use App\Modules\ClinicRegistration\Infrastructure\ClinicRegistrationServiceProvider;
use App\Modules\Commercial\Infrastructure\CommercialServiceProvider;
use App\Modules\Onboarding\Infrastructure\OnboardingServiceProvider;
use App\Modules\PlatformAdministration\Infrastructure\PlatformAdministrationServiceProvider;
use App\Modules\SubscriptionBilling\Infrastructure\SubscriptionBillingServiceProvider;
use App\Modules\TenantManagement\Infrastructure\TenantManagementServiceProvider;
use App\Modules\WebsiteBuilder\Infrastructure\WebsiteBuilderServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    TenantManagementServiceProvider::class,
    WebsiteBuilderServiceProvider::class,
    OnboardingServiceProvider::class,
    ClinicRegistrationServiceProvider::class,
    CommercialServiceProvider::class,
    PlatformAdministrationServiceProvider::class,
    SubscriptionBillingServiceProvider::class,
    BookingServiceProvider::class,
];
