<?php

declare(strict_types=1);

use App\Modules\Onboarding\Infrastructure\OnboardingServiceProvider;
use App\Modules\PlatformAdministration\Infrastructure\PlatformAdministrationServiceProvider;
use App\Modules\TenantManagement\Infrastructure\TenantManagementServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    TenantManagementServiceProvider::class,
    OnboardingServiceProvider::class,
    PlatformAdministrationServiceProvider::class,
];
