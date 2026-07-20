<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\RateLimiting\RequestProtectionRateLimiters;
use App\Support\Production\ProductionEnvironmentGuard;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->make(RequestProtectionRateLimiters::class)->register();
        $this->app->make(ProductionEnvironmentGuard::class)->validate();
    }
}
