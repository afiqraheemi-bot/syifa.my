<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\RateLimiting\RequestProtectionRateLimiters;
use App\Support\Infrastructure\InfrastructureReadinessValidator;
use App\Support\Infrastructure\RuntimeDependencyHealthChecker;
use App\Support\Production\ProductionEnvironmentGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(InfrastructureReadinessValidator::class);
        $this->app->singleton(RuntimeDependencyHealthChecker::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        $this->app->make(RequestProtectionRateLimiters::class)->register();
        $this->app->make(ProductionEnvironmentGuard::class)->validate();
    }
}
