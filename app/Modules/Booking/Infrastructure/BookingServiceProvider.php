<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure;

use App\Modules\Booking\Contracts\Repositories\BookingRepositoryInterface;
use App\Modules\Booking\Contracts\Repositories\ServiceRepositoryInterface;
use App\Modules\Booking\Infrastructure\Persistence\Mappers\BookingPersistenceMapper;
use App\Modules\Booking\Infrastructure\Persistence\Mappers\ServicePersistenceMapper;
use App\Modules\Booking\Infrastructure\Persistence\Repositories\PostgresBookingRepository;
use App\Modules\Booking\Infrastructure\Persistence\Repositories\PostgresServiceRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class BookingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BookingPersistenceMapper::class);
        $this->app->singleton(
            BookingRepositoryInterface::class,
            static fn (Application $application): PostgresBookingRepository => new PostgresBookingRepository(
                $application->make('db')->connection(),
                $application->make(BookingPersistenceMapper::class),
            ),
        );
        $this->app->singleton(ServicePersistenceMapper::class);
        $this->app->singleton(
            ServiceRepositoryInterface::class,
            static fn (Application $application): PostgresServiceRepository => new PostgresServiceRepository(
                $application->make('db')->connection(),
                $application->make(ServicePersistenceMapper::class),
            ),
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/booking'));
    }
}
