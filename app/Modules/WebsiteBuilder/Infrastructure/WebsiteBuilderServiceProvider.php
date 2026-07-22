<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure;

use App\Modules\Booking\Contracts\ClinicOperationalTime\ClinicOperationalTimeReaderInterface;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\ClinicRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteRepositoryInterface;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\ClinicPersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsitePersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsiteSectionPersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsiteSeoConfigurationPersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Repositories\PostgresClinicRepository;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Repositories\PostgresWebsiteRepository;
use App\Modules\WebsiteBuilder\Infrastructure\Queries\BookingClinicOperationalTimeAdapter;
use App\Modules\WebsiteBuilder\Infrastructure\Queries\PostgresWebsiteReadAdapter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class WebsiteBuilderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ClinicPersistenceMapper::class);
        $this->app->singleton(WebsitePersistenceMapper::class);
        $this->app->singleton(WebsiteSectionPersistenceMapper::class);
        $this->app->singleton(WebsiteSeoConfigurationPersistenceMapper::class);
        $this->app->singleton(
            ClinicRepositoryInterface::class,
            static fn (Application $application): PostgresClinicRepository => new PostgresClinicRepository(
                $application->make('db')->connection(),
                $application->make(ClinicPersistenceMapper::class),
            ),
        );
        $this->app->singleton(
            WebsiteRepositoryInterface::class,
            static fn (Application $application): PostgresWebsiteRepository => new PostgresWebsiteRepository(
                $application->make('db')->connection(),
                $application->make(WebsitePersistenceMapper::class),
                $application->make(WebsiteSectionPersistenceMapper::class),
                $application->make(WebsiteSeoConfigurationPersistenceMapper::class),
            ),
        );
        $this->app->singleton(
            WebsiteReadInterface::class,
            static fn (Application $application): PostgresWebsiteReadAdapter => new PostgresWebsiteReadAdapter($application->make('db')->connection()),
        );
        $this->app->singleton(
            ClinicOperationalTimeReaderInterface::class,
            BookingClinicOperationalTimeAdapter::class,
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/website_builder'));
    }
}
