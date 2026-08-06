<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure;

use App\Modules\Booking\Contracts\ClinicOperationalTime\ClinicOperationalTimeReaderInterface;
use App\Modules\Booking\Contracts\Queries\ActiveServiceCatalogueReaderInterface;
use App\Modules\Booking\Contracts\Queries\AvailableSlotReaderInterface;
use App\Modules\Booking\Contracts\Queries\PublicBookingFormReaderInterface;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\SubscriptionEntitlementLookupInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionSummaryReadInterface;
use App\Modules\WebsiteBuilder\Application\CustomDomain\ManageCustomDomainService;
use App\Modules\WebsiteBuilder\Application\Delivery\AvailabilityDeliveryService;
use App\Modules\WebsiteBuilder\Application\Delivery\BookingDeliveryService;
use App\Modules\WebsiteBuilder\Application\Delivery\PlatformLegalContentProviderInterface;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicAssetUrlResolverInterface;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicAvailabilityCacheInterface;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContextFactoryInterface;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicWebsiteDocumentFactory;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicWebsiteRenderModelProviderInterface;
use App\Modules\WebsiteBuilder\Application\Provisioning\ProvisionWebsiteFoundationService;
use App\Modules\WebsiteBuilder\Application\Provisioning\ReserveProvisionedWebsiteAddressService;
use App\Modules\WebsiteBuilder\Application\PublicTemplate\StaticPublicTemplateCatalog;
use App\Modules\WebsiteBuilder\Application\Rendering\PublicWebsiteRenderProjector;
use App\Modules\WebsiteBuilder\Application\WebsiteAddress\WebsiteSubdomainPolicy;
use App\Modules\WebsiteBuilder\Application\WebsiteDraft\WebsiteDraftSectionCodec;
use App\Modules\WebsiteBuilder\Contracts\Assets\PublicWebsiteAssetReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Assets\WebsiteAssetBinaryStorageInterface;
use App\Modules\WebsiteBuilder\Contracts\CustomDomain\CustomDomainRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\CustomDomain\DomainControlVerifierInterface;
use App\Modules\WebsiteBuilder\Contracts\Delivery\BookingSubmissionGatewayInterface;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicAvailabilityReaderInterface;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingFormConfigurationReaderInterface;
use App\Modules\WebsiteBuilder\Contracts\Delivery\WebsiteTenantResolverInterface;
use App\Modules\WebsiteBuilder\Contracts\Provisioning\ProvisionWebsiteFoundationInterface;
use App\Modules\WebsiteBuilder\Contracts\Provisioning\ReserveProvisionedWebsiteAddressInterface;
use App\Modules\WebsiteBuilder\Contracts\PublicAddress\PublicWebsiteAddressAvailabilityInterface;
use App\Modules\WebsiteBuilder\Contracts\PublicAddress\WebsitePublicAddressReadInterface;
use App\Modules\WebsiteBuilder\Contracts\PublicAddress\WebsitePublicAddressRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\PublicTemplate\PublicTemplateCatalogInterface;
use App\Modules\WebsiteBuilder\Contracts\Queries\ActiveServiceReferenceReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Queries\ClinicSummaryReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsitePublishedSnapshotReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteSeoSummaryReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\ClinicBookingDateOverrideRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\ClinicRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteDraftRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\SyifaAi\SyifaAiProviderInterface;
use App\Modules\WebsiteBuilder\Contracts\SyifaAi\SyifaAiUsageRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Transactions\ClinicTransactionInterface;
use App\Modules\WebsiteBuilder\Contracts\Transactions\WebsitePublicationTransactionInterface;
use App\Modules\WebsiteBuilder\Infrastructure\Assets\LaravelWebsiteAssetBinaryStorage;
use App\Modules\WebsiteBuilder\Infrastructure\Assets\PostgresPublicWebsiteAssetReadAdapter;
use App\Modules\WebsiteBuilder\Infrastructure\CustomDomain\NativeDnsDomainControlVerifier;
use App\Modules\WebsiteBuilder\Infrastructure\Delivery\BookingAvailableSlotReaderAdapter;
use App\Modules\WebsiteBuilder\Infrastructure\Delivery\BookingFormConfigurationReaderAdapter;
use App\Modules\WebsiteBuilder\Infrastructure\Delivery\BookingSubmissionGatewayAdapter;
use App\Modules\WebsiteBuilder\Infrastructure\Delivery\ConfiguredPlatformLegalContentProvider;
use App\Modules\WebsiteBuilder\Infrastructure\Delivery\LaravelPublicAvailabilityCache;
use App\Modules\WebsiteBuilder\Infrastructure\Delivery\OriginPublicAssetUrlResolver;
use App\Modules\WebsiteBuilder\Infrastructure\Delivery\PostgresPublicSiteContextFactory;
use App\Modules\WebsiteBuilder\Infrastructure\Delivery\PostgresPublicWebsiteRenderModelProvider;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\ClinicPersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsiteAssetPersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsitePersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsiteSectionPersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsiteSeoConfigurationPersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Queries\PostgresPublicWebsiteAddressAvailability;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Repositories\PostgresClinicBookingDateOverrideRepository;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Repositories\PostgresClinicRepository;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Repositories\PostgresCustomDomainRepository;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Repositories\PostgresWebsiteDraftRepository;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Repositories\PostgresWebsitePublicAddressRepository;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Repositories\PostgresWebsiteRepository;
use App\Modules\WebsiteBuilder\Infrastructure\Queries\BookingActiveServiceReferenceAdapter;
use App\Modules\WebsiteBuilder\Infrastructure\Queries\BookingClinicOperationalTimeAdapter;
use App\Modules\WebsiteBuilder\Infrastructure\Queries\PostgresClinicSummaryReadAdapter;
use App\Modules\WebsiteBuilder\Infrastructure\Queries\PostgresWebsitePublishedSnapshotReadAdapter;
use App\Modules\WebsiteBuilder\Infrastructure\Queries\PostgresWebsiteReadAdapter;
use App\Modules\WebsiteBuilder\Infrastructure\Queries\PostgresWebsiteSeoSummaryReadAdapter;
use App\Modules\WebsiteBuilder\Infrastructure\Queries\PostgresWebsiteTenantResolver;
use App\Modules\WebsiteBuilder\Infrastructure\SyifaAi\OpenAiSyifaAiProvider;
use App\Modules\WebsiteBuilder\Infrastructure\SyifaAi\PostgresSyifaAiUsageRepository;
use App\Modules\WebsiteBuilder\Infrastructure\Transactions\PostgresClinicTransaction;
use App\Modules\WebsiteBuilder\Infrastructure\Transactions\PostgresWebsitePublicationTransaction;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class WebsiteBuilderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            WebsiteAssetBinaryStorageInterface::class,
            LaravelWebsiteAssetBinaryStorage::class,
        );
        $this->app->singleton(
            PublicWebsiteAssetReadInterface::class,
            static fn (Application $application): PostgresPublicWebsiteAssetReadAdapter => new PostgresPublicWebsiteAssetReadAdapter(
                $application->make('db')->connection(),
                $application->make('filesystem'),
            ),
        );
        $this->app->singleton(
            PublicWebsiteAddressAvailabilityInterface::class,
            static fn ($application): PostgresPublicWebsiteAddressAvailability => new PostgresPublicWebsiteAddressAvailability(
                $application->make('db')->connection(),
                $application->make(WebsitePublicAddressRepositoryInterface::class),
                $application->make(WebsiteSubdomainPolicy::class),
            ),
        );
        $this->app->singleton(PublicTemplateCatalogInterface::class, StaticPublicTemplateCatalog::class);
        $this->app->singleton(
            CustomDomainRepositoryInterface::class,
            static fn (Application $application): PostgresCustomDomainRepository => new PostgresCustomDomainRepository(
                $application->make('db')->connection(),
            ),
        );
        $this->app->singleton(
            DomainControlVerifierInterface::class,
            static fn (): NativeDnsDomainControlVerifier => new NativeDnsDomainControlVerifier(
                array_values(array_filter(array_map(
                    static fn (mixed $target): string => strtolower(rtrim(trim((string) $target), '.')),
                    (array) config('public_website_delivery.custom_domain_targets', []),
                ))),
            ),
        );
        $this->app->singleton(
            ManageCustomDomainService::class,
            static fn (Application $application): ManageCustomDomainService => new ManageCustomDomainService(
                $application->make(CustomDomainRepositoryInterface::class),
                $application->make(DomainControlVerifierInterface::class),
                $application->make(SubscriptionEntitlementLookupInterface::class),
                $application->make(WebsiteReadInterface::class),
                (string) config('commercial.capabilities.custom_domain', ''),
            ),
        );
        $this->app->singleton(ClinicPersistenceMapper::class);
        $this->app->singleton(WebsitePersistenceMapper::class);
        $this->app->singleton(WebsiteAssetPersistenceMapper::class);
        $this->app->singleton(WebsiteSectionPersistenceMapper::class);
        $this->app->singleton(WebsiteSeoConfigurationPersistenceMapper::class);
        $this->app->singleton(WebsiteSubdomainPolicy::class, static fn (): WebsiteSubdomainPolicy => new WebsiteSubdomainPolicy(
            (string) config('public_website_delivery.base_domain'),
        ));
        $this->app->singleton(
            WebsitePublicAddressRepositoryInterface::class,
            static fn (Application $application): PostgresWebsitePublicAddressRepository => new PostgresWebsitePublicAddressRepository(
                $application->make('db')->connection(),
                is_string(config('public_website_delivery.local_alias_base_domain'))
                    ? (string) config('public_website_delivery.local_alias_base_domain')
                    : null,
                is_int(config('public_website_delivery.local_alias_port'))
                    ? (int) config('public_website_delivery.local_alias_port')
                    : null,
                (string) config('public_website_delivery.base_domain'),
            ),
        );
        $this->app->alias(
            WebsitePublicAddressRepositoryInterface::class,
            WebsitePublicAddressReadInterface::class,
        );
        $this->app->singleton(ProvisionWebsiteFoundationInterface::class, ProvisionWebsiteFoundationService::class);
        $this->app->singleton(ReserveProvisionedWebsiteAddressInterface::class, ReserveProvisionedWebsiteAddressService::class);
        $this->app->singleton(
            PublicSiteContextFactoryInterface::class,
            static fn (Application $application): PostgresPublicSiteContextFactory => new PostgresPublicSiteContextFactory(
                $application->make(WebsitePublicAddressReadInterface::class),
                $application->make(SubscriptionSummaryReadInterface::class),
                (array) config('public_website_delivery.sites', []),
                (bool) config('public_website_delivery.runtime_addressing', true),
                is_string(config('public_website_delivery.local_alias_base_domain'))
                    ? (string) config('public_website_delivery.local_alias_base_domain')
                    : null,
                is_int(config('public_website_delivery.local_alias_port'))
                    ? (int) config('public_website_delivery.local_alias_port')
                    : null,
                (string) config('public_website_delivery.base_domain'),
            ),
        );
        $this->app->singleton(
            PublicAssetUrlResolverInterface::class,
            static fn (): OriginPublicAssetUrlResolver => new OriginPublicAssetUrlResolver((string) config('public_website_delivery.asset_origin')),
        );
        $this->app->singleton(
            PublicWebsiteRenderModelProviderInterface::class,
            static fn (Application $application): PostgresPublicWebsiteRenderModelProvider => new PostgresPublicWebsiteRenderModelProvider(
                $application->make(PostgresWebsiteRepository::class),
                $application->make(PublicWebsiteRenderProjector::class),
                $application->make(SubscriptionSummaryReadInterface::class),
            ),
        );
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
            ClinicBookingDateOverrideRepositoryInterface::class,
            static fn (Application $application): PostgresClinicBookingDateOverrideRepository => new PostgresClinicBookingDateOverrideRepository(
                $application->make('db')->connection(),
            ),
        );
        $this->app->singleton(
            ClinicSummaryReadInterface::class,
            static fn (Application $application): PostgresClinicSummaryReadAdapter => new PostgresClinicSummaryReadAdapter(
                $application->make('db')->connection(),
            ),
        );
        $this->app->singleton(
            ClinicTransactionInterface::class,
            static fn (Application $application): PostgresClinicTransaction => new PostgresClinicTransaction($application->make('db')->connection()),
        );
        $this->app->singleton(
            WebsitePublicationTransactionInterface::class,
            static fn (Application $application): PostgresWebsitePublicationTransaction => new PostgresWebsitePublicationTransaction($application->make('db')->connection()),
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
        $this->app->singleton(
            WebsiteDraftRepositoryInterface::class,
            static fn (Application $application): PostgresWebsiteDraftRepository => new PostgresWebsiteDraftRepository(
                $application->make('db')->connection(),
                $application->make(WebsiteDraftSectionCodec::class),
            ),
        );
        $this->app->singleton(SyifaAiProviderInterface::class, OpenAiSyifaAiProvider::class);
        $this->app->singleton(
            SyifaAiUsageRepositoryInterface::class,
            static fn (Application $application): PostgresSyifaAiUsageRepository => new PostgresSyifaAiUsageRepository(
                $application->make('db')->connection(),
            ),
        );
        $this->app->singleton(
            ActiveServiceReferenceReadInterface::class,
            BookingActiveServiceReferenceAdapter::class,
        );
        $this->app->alias(WebsiteRepositoryInterface::class, PostgresWebsiteRepository::class);
        $this->app->singleton(
            WebsiteReadInterface::class,
            static fn (Application $application): PostgresWebsiteReadAdapter => new PostgresWebsiteReadAdapter($application->make('db')->connection()),
        );
        $this->app->singleton(
            WebsiteSeoSummaryReadInterface::class,
            static fn (Application $application): PostgresWebsiteSeoSummaryReadAdapter => new PostgresWebsiteSeoSummaryReadAdapter(
                $application->make('db')->connection(),
            ),
        );
        $this->app->singleton(
            WebsitePublishedSnapshotReadInterface::class,
            static fn (Application $application): PostgresWebsitePublishedSnapshotReadAdapter => new PostgresWebsitePublishedSnapshotReadAdapter($application->make('db')->connection()),
        );
        $this->app->singleton(
            ClinicOperationalTimeReaderInterface::class,
            BookingClinicOperationalTimeAdapter::class,
        );

        // Sprint 2 (ADR-029/030): the real adapter, calling the Booking Engine's
        // SubmitBookingService. Replaces the Sprint 1 Stub — no other class
        // (Controllers, BookingDeliveryService, ViewModels, Blade) changes.
        $this->app->singleton(BookingSubmissionGatewayInterface::class, BookingSubmissionGatewayAdapter::class);

        // ADR-029: closes the Website -> Tenant identity gap. Real from day one —
        // the data it needs (websites.tenant_id) already exists.
        $this->app->singleton(
            WebsiteTenantResolverInterface::class,
            static fn (Application $application): PostgresWebsiteTenantResolver => new PostgresWebsiteTenantResolver($application->make('db')->connection()),
        );

        // Sprint 2 (ADR-028/029): the real adapter over the Booking Engine's
        // existing AvailableSlotReaderInterface. Replaces the Sprint 1 Fixture —
        // no other class (AvailabilityDeliveryService, ViewModels, Controllers,
        // Blade) changes.
        $this->app->singleton(
            PublicAvailabilityReaderInterface::class,
            static fn (Application $application): BookingAvailableSlotReaderAdapter => new BookingAvailableSlotReaderAdapter(
                $application->make(AvailableSlotReaderInterface::class),
            ),
        );

        // Sprint 2 (ADR-027/031): the real adapter, wrapping Booking's own
        // PublicBookingFormReaderInterface (never a raw Booking repository
        // directly). Replaces the Sprint 1 Fixture — no other class changes.
        $this->app->singleton(
            PublicBookingFormConfigurationReaderInterface::class,
            static fn (Application $application): BookingFormConfigurationReaderAdapter => new BookingFormConfigurationReaderAdapter(
                $application->make(PublicBookingFormReaderInterface::class),
                $application->make(ActiveServiceCatalogueReaderInterface::class),
            ),
        );

        // Sprint 2 (ADR-028): the real, Illuminate-backed cache implementation,
        // kept out of the Application layer per this module's own boundary
        // (Application/Delivery never references the framework directly).
        $this->app->singleton(PublicAvailabilityCacheInterface::class, LaravelPublicAvailabilityCache::class);

        $this->app->singleton(AvailabilityDeliveryService::class);
        $this->app->singleton(BookingDeliveryService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/website_builder'));
    }
}
