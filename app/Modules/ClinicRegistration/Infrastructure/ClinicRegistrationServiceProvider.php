<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Infrastructure;

use Illuminate\Support\ServiceProvider;

final class ClinicRegistrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            config_path('clinic_registration.php'),
            'clinic_registration',
        );
    }

    public function boot(): void
    {
        if ((bool) config('clinic_registration.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/routes/clinic_registration.php');
        }
    }
}
