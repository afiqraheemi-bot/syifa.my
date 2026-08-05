<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure;

use App\Modules\Booking\Application\Availability\AvailableSlotReader;
use App\Modules\Booking\Application\Availability\ClinicSlotGenerator;
use App\Modules\Booking\Application\BookingHistoryIdentifierGenerator;
use App\Modules\Booking\Application\BookingHistoryIdentifierGeneratorInterface;
use App\Modules\Booking\Application\BookingIdentifierGenerator;
use App\Modules\Booking\Application\BookingIdentifierGeneratorInterface;
use App\Modules\Booking\Application\BookingOwnerAuthorization;
use App\Modules\Booking\Application\BookingReferenceGenerator;
use App\Modules\Booking\Application\BookingReferenceGeneratorInterface;
use App\Modules\Booking\Application\ClinicOwnerBookingOperations;
use App\Modules\Booking\Application\Provisioning\ProvisionBookingFoundationService;
use App\Modules\Booking\Application\Queries\ActiveServiceCatalogueReader;
use App\Modules\Booking\Application\Queries\ActiveServiceReferenceReader;
use App\Modules\Booking\Application\Queries\PublicBookingFormReader;
use App\Modules\Booking\Application\ServiceSetup\ManageServiceSetupService;
use App\Modules\Booking\Contracts\Capacity\SlotCapacityReservationInterface;
use App\Modules\Booking\Contracts\Clock\BookingClockInterface;
use App\Modules\Booking\Contracts\Operations\ClinicOwnerBookingOperationsInterface;
use App\Modules\Booking\Contracts\Provisioning\ProvisionBookingFoundationInterface;
use App\Modules\Booking\Contracts\Queries\ActiveServiceCatalogueReaderInterface;
use App\Modules\Booking\Contracts\Queries\ActiveServiceReferenceReaderInterface;
use App\Modules\Booking\Contracts\Queries\AvailableSlotReaderInterface;
use App\Modules\Booking\Contracts\Queries\ClinicOwnerBookingReadInterface;
use App\Modules\Booking\Contracts\Queries\PublicBookingFormReaderInterface;
use App\Modules\Booking\Contracts\Repositories\BookingFormConfigurationRepositoryInterface;
use App\Modules\Booking\Contracts\Repositories\BookingHistoryRepositoryInterface;
use App\Modules\Booking\Contracts\Repositories\BookingRepositoryInterface;
use App\Modules\Booking\Contracts\Repositories\ServiceRepositoryInterface;
use App\Modules\Booking\Contracts\ServiceSetup\ServiceSetupAuditInterface;
use App\Modules\Booking\Contracts\Transactions\BookingTransactionInterface;
use App\Modules\Booking\Infrastructure\Persistence\Mappers\BookingFormConfigurationPersistenceMapper;
use App\Modules\Booking\Infrastructure\Persistence\Mappers\BookingPersistenceMapper;
use App\Modules\Booking\Infrastructure\Persistence\Mappers\ServicePersistenceMapper;
use App\Modules\Booking\Infrastructure\Persistence\Queries\PostgresClinicOwnerBookingReadAdapter;
use App\Modules\Booking\Infrastructure\Persistence\Repositories\PostgresBookingFormConfigurationRepository;
use App\Modules\Booking\Infrastructure\Persistence\Repositories\PostgresBookingHistoryRepository;
use App\Modules\Booking\Infrastructure\Persistence\Repositories\PostgresBookingRepository;
use App\Modules\Booking\Infrastructure\Persistence\Repositories\PostgresServiceRepository;
use App\Modules\Booking\Infrastructure\Persistence\Repositories\PostgresSlotCapacityReservation;
use App\Modules\Booking\Infrastructure\Transactions\PostgresBookingTransaction;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\SubscriptionEntitlementLookupInterface;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class BookingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProvisionBookingFoundationInterface::class, ProvisionBookingFoundationService::class);
        $this->app->singleton(BookingIdentifierGeneratorInterface::class, BookingIdentifierGenerator::class);
        $this->app->singleton(BookingHistoryIdentifierGeneratorInterface::class, BookingHistoryIdentifierGenerator::class);
        $this->app->singleton(ClinicSlotGenerator::class);
        $this->app->singleton(BookingOwnerAuthorization::class);
        $this->app->singleton(AvailableSlotReaderInterface::class, AvailableSlotReader::class);
        $this->app->singleton(ActiveServiceReferenceReaderInterface::class, ActiveServiceReferenceReader::class);
        $this->app->singleton(ActiveServiceCatalogueReaderInterface::class, ActiveServiceCatalogueReader::class);
        $this->app->singleton(PublicBookingFormReaderInterface::class, PublicBookingFormReader::class);
        $this->app->singleton(BookingReferenceGeneratorInterface::class, BookingReferenceGenerator::class);
        $this->app->singleton(BookingClockInterface::class, SystemBookingClock::class);
        $this->app->singleton(ClinicOwnerBookingOperationsInterface::class, ClinicOwnerBookingOperations::class);
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
            SlotCapacityReservationInterface::class,
            static fn (Application $application): PostgresSlotCapacityReservation => new PostgresSlotCapacityReservation($application->make('db')->connection()),
        );
        $this->app->singleton(
            BookingHistoryRepositoryInterface::class,
            static fn (Application $application): PostgresBookingHistoryRepository => new PostgresBookingHistoryRepository($application->make('db')->connection()),
        );
        $this->app->singleton(
            ClinicOwnerBookingReadInterface::class,
            static fn (Application $application): PostgresClinicOwnerBookingReadAdapter => new PostgresClinicOwnerBookingReadAdapter($application->make('db')->connection()),
        );
        $this->app->singleton(
            ServiceRepositoryInterface::class,
            static fn (Application $application): PostgresServiceRepository => new PostgresServiceRepository(
                $application->make('db')->connection(),
                $application->make(ServicePersistenceMapper::class),
            ),
        );
        $this->app->singleton(
            ManageServiceSetupService::class,
            static fn (Application $application): ManageServiceSetupService => new ManageServiceSetupService(
                $application->make(ServiceRepositoryInterface::class),
                $application->make(BookingTransactionInterface::class),
                $application->make(ServiceSetupAuditInterface::class),
                $application->make(SubscriptionEntitlementLookupInterface::class),
                (string) config('commercial.capabilities.service_setup', 'service_setup'),
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
