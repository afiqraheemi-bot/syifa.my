<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure;

use App\Modules\Booking\Application\BookingIdentifierGenerator;
use App\Modules\Booking\Application\BookingIdentifierGeneratorInterface;
use App\Modules\Booking\Application\BookingReferenceGenerator;
use App\Modules\Booking\Application\BookingReferenceGeneratorInterface;
use App\Modules\Booking\Contracts\Clock\BookingClockInterface;
use App\Modules\Booking\Contracts\Repositories\BookingFormConfigurationRepositoryInterface;
use App\Modules\Booking\Contracts\Repositories\BookingRepositoryInterface;
use App\Modules\Booking\Contracts\Repositories\ServiceRepositoryInterface;
use App\Modules\Booking\Contracts\Transactions\BookingTransactionInterface;
use App\Modules\Booking\Infrastructure\Persistence\Mappers\BookingFormConfigurationPersistenceMapper;
use App\Modules\Booking\Infrastructure\Persistence\Mappers\BookingPersistenceMapper;
use App\Modules\Booking\Infrastructure\Persistence\Mappers\ServicePersistenceMapper;
use App\Modules\Booking\Infrastructure\Persistence\Repositories\PostgresBookingFormConfigurationRepository;
use App\Modules\Booking\Infrastructure\Persistence\Repositories\PostgresBookingRepository;
use App\Modules\Booking\Infrastructure\Persistence\Repositories\PostgresServiceRepository;
use App\Modules\Booking\Infrastructure\Transactions\PostgresBookingTransaction;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class BookingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BookingIdentifierGeneratorInterface::class, BookingIdentifierGenerator::class);
        $this->app->singleton(BookingReferenceGeneratorInterface::class, BookingReferenceGenerator::class);
        $this->app->singleton(BookingClockInterface::class, SystemBookingClock::class);
        $this->app->singleton(
            BookingTransactionInterface::class,
            static fn (Application $application): PostgresBookingTransaction => new PostgresBookingTransaction(
                $application->make('db')->connection(),
            ),
        );
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
        $this->app->singleton(BookingFormConfigurationPersistenceMapper::class);
        $this->app->singleton(
            BookingFormConfigurationRepositoryInterface::class,
            static fn (Application $application): PostgresBookingFormConfigurationRepository => new PostgresBookingFormConfigurationRepository(
                $application->make('db')->connection(),
                $application->make(BookingFormConfigurationPersistenceMapper::class),
            ),
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/booking'));
    }
}
