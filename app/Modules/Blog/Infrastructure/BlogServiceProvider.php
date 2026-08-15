<?php

declare(strict_types=1);

namespace App\Modules\Blog\Infrastructure;

use App\Modules\Blog\Application\BlogAuthorization;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\SubscriptionEntitlementLookupInterface;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class BlogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BlogAuthorization::class, static fn (Application $app): BlogAuthorization => new BlogAuthorization(
            $app->make(SubscriptionEntitlementLookupInterface::class),
            $app->make('db')->connection(),
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/blog'));
    }
}
