<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure;

use App\Modules\Booking\Contracts\ClinicOperationalTime\ClinicOperationalTimeReaderInterface;
use App\Modules\WebsiteBuilder\Application\Delivery\PlatformLegalContentProviderInterface;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicAssetUrlResolverInterface;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContextFactoryInterface;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicWebsiteDocumentFactory;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicWebsiteRenderModelProviderInterface;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsitePublishedSnapshotReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\ClinicRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Transactions\ClinicTransactionInterface;
use App\Modules\WebsiteBuilder\Infrastructure\Delivery\ConfiguredPlatformLegalContentProvider;
use App\Modules\WebsiteBuilder\Infrastructure\Delivery\ConfiguredPublicSiteContextFactory;
use App\Modules\WebsiteBuilder\Infrastructure\Delivery\OriginPublicAssetUrlResolver;
use App\Modules\WebsiteBuilder\Infrastructure\Delivery\PostgresPublicWebsiteRenderModelProvider;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\ClinicPersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsiteAssetPersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsitePersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsiteSectionPersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsiteSeoConfigurationPersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Repositories\PostgresClinicRepository;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Repositories\PostgresWebsiteRepository;
use App\Modules\WebsiteBuilder\Infrastructure\Queries\BookingClinicOperationalTimeAdapter;
use App\Modules\WebsiteBuilder\Infrastructure\Queries\PostgresWebsitePublishedSnapshotReadAdapter;
use App\Modules\WebsiteBuilder\Infrastructure\Queries\PostgresWebsiteReadAdapter;
use App\Modules\WebsiteBuilder\Infrastructure\Transactions\PostgresClinicTransaction;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class WebsiteBuilderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ClinicPersistenceMapper::class);
        $this->app->singleton(WebsitePersistenceMapper::class);
        $this->app->singleton(WebsiteAssetPersistenceMapper::class);
        $this->app->singleton(WebsiteSectionPersistenceMapper::class);
        $this->app->singleton(WebsiteSeoConfigurationPersistenceMapper::class);
        $this->app->singleton(
            PublicSiteContextFactoryInterface::class,
            static fn (): ConfiguredPublicSiteContextFactory => new ConfiguredPublicSiteContextFactory((array) config('public_website_delivery.sites', [])),
        );
        $this->app->singleton(
            PublicAssetUrlResolverInterface::class,
            static fn (): OriginPublicAssetUrlResolver => new OriginPublicAssetUrlResolver((string) config('public_website_delivery.asset_origin')),
        );
        $this->app->singleton(PublicWebsiteRenderModelProviderInterface::class, PostgresPublicWebsiteRenderModelProvider::class);
        $this->app->singleton(
            PlatformLegalContentProviderInterface::class,
            static fn (): ConfiguredPlatformLegalContentProvider => new ConfiguredPlatformLegalContentProvider((array) config('public_website_delivery.legal', [])),
        );
        $this->app->singleton(PublicWebsiteDocumentFactory::class);
        $this->app->singleton(
            ClinicRepositoryInterface::class,
            static fn (Application $application): PostgresClinicRepository => new PostgresClinicRepository(
                $application->make('db')->connection(),
                $application->make(ClinicPersistenceMapper::class),
            ),
        );
        $this->app->singleton(
            ClinicTransactionInterface::class,
            static fn (Application $application): PostgresClinicTransaction => new PostgresClinicTransaction($application->make('db')->connection()),
        );
        $this->app->singleton(
            WebsiteRepositoryInterface::class,
            static fn (Application $application): PostgresWebsiteRepository => new PostgresWebsiteRepository(
                $application->make('db')->connection(),
                $application->make(WebsitePersistenceMapper::class),
                $application->make(WebsiteSectionPersistenceMapper::class),
                $application->make(WebsiteSeoConfigurationPersistenceMapper::class),
                $application->make(WebsiteAssetPersistenceMapper::class),
            ),
        );
        $this->app->alias(WebsiteRepositoryInterface::class, PostgresWebsiteRepository::class);
        $this->app->singleton(
            WebsiteReadInterface::class,
            static fn (Application $application): PostgresWebsiteReadAdapter => new PostgresWebsiteReadAdapter($application->make('db')->connection()),
        );
        $this->app->singleton(
            WebsitePublishedSnapshotReadInterface::class,
            static fn (Application $application): PostgresWebsitePublishedSnapshotReadAdapter => new PostgresWebsitePublishedSnapshotReadAdapter($application->make('db')->connection()),
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
