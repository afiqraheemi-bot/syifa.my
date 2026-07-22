<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure;

use App\Modules\Booking\Contracts\ClinicOperationalTime\ClinicOperationalTimeReaderInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\ClinicRepositoryInterface;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\ClinicPersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Repositories\PostgresClinicRepository;
use App\Modules\WebsiteBuilder\Infrastructure\Queries\BookingClinicOperationalTimeAdapter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class WebsiteBuilderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ClinicPersistenceMapper::class);
        $this->app->singleton(
            ClinicRepositoryInterface::class,
            static fn (Application $application): PostgresClinicRepository => new PostgresClinicRepository(
                $application->make('db')->connection(),
                $application->make(ClinicPersistenceMapper::class),
            ),
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
