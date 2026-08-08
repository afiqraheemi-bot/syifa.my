<?php

declare(strict_types=1);

namespace App\Modules\AcquisitionOffer\Infrastructure;

use App\Modules\AcquisitionOffer\Application\ClaimCommercialOfferService;
use App\Modules\AcquisitionOffer\Application\CommercialOfferDataAssembler;
use App\Modules\AcquisitionOffer\Application\CommercialOfferIdentifierGenerator;
use App\Modules\AcquisitionOffer\Application\CommercialOfferIdentifierGeneratorInterface;
use App\Modules\AcquisitionOffer\Application\PrepareCommercialOfferService;
use App\Modules\AcquisitionOffer\Application\TrustedCommercialOfferConsumers;
use App\Modules\AcquisitionOffer\Contracts\Checkout\CommercialOfferCheckoutInterface;
use App\Modules\AcquisitionOffer\Contracts\Events\CommercialOfferEventPublisherInterface;
use App\Modules\AcquisitionOffer\Contracts\ReferenceData\BillingCycleQueryInterface;
use App\Modules\AcquisitionOffer\Contracts\ReferenceData\PlanOfferingQueryInterface;
use App\Modules\AcquisitionOffer\Contracts\ReferenceData\PlanQueryInterface;
use App\Modules\AcquisitionOffer\Contracts\ReferenceData\PricingQueryInterface;
use App\Modules\AcquisitionOffer\Contracts\Renewal\PrepareRenewalOfferInterface;
use App\Modules\AcquisitionOffer\Contracts\Repositories\CommercialOfferRepositoryInterface;
use App\Modules\AcquisitionOffer\Contracts\Transactions\CommercialTransactionInterface;
use App\Modules\AcquisitionOffer\Infrastructure\Events\LaravelCommercialOfferEventPublisher;
use App\Modules\AcquisitionOffer\Infrastructure\Persistence\Mappers\CommercialOfferPersistenceMapper;
use App\Modules\AcquisitionOffer\Infrastructure\Persistence\Repositories\PostgresCommercialOfferRepository;
use App\Modules\AcquisitionOffer\Infrastructure\ReferenceData\SubscriptionBillingBillingCycleQueryAdapter;
use App\Modules\AcquisitionOffer\Infrastructure\ReferenceData\SubscriptionBillingPlanOfferingQueryAdapter;
use App\Modules\AcquisitionOffer\Infrastructure\ReferenceData\SubscriptionBillingPlanQueryAdapter;
use App\Modules\AcquisitionOffer\Infrastructure\ReferenceData\SubscriptionBillingPricingQueryAdapter;
use App\Modules\AcquisitionOffer\Infrastructure\Renewal\PostgresPrepareRenewalOfferAdapter;
use App\Modules\AcquisitionOffer\Infrastructure\Transactions\PostgresCommercialTransaction;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RenewalCommercialContextReadInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Not the pricing catalogue — that is `SubscriptionBilling\Application\CommercialCatalogue`
 * (Plan/PlanOffering/BillingOption/CapabilityDefinition, the admin-facing system
 * of record). This module is the smaller "Commercial Offer" bounded context: a
 * point-in-time offer/checkout snapshot (prepare, claim, cancel, expire) used
 * during public Clinic Registration. It only ever reads the catalogue through
 * its own `Contracts\ReferenceData` interfaces — it never edits it. Renamed
 * from `App\Modules\Commercial` on 2026-08-08 to resolve a naming collision
 * with `CommercialCatalogue` (see `docs/37_MASTER_ARCHITECTURE_PROGRESS.md`).
 */
final class AcquisitionOfferServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('acquisition_offer.php'), 'acquisition_offer');

        $this->app->singleton(CommercialOfferDataAssembler::class);
        $this->app->singleton(CommercialOfferIdentifierGeneratorInterface::class, CommercialOfferIdentifierGenerator::class);
        $this->app->singleton(
            CommercialOfferEventPublisherInterface::class,
            static fn (Application $application): LaravelCommercialOfferEventPublisher => new LaravelCommercialOfferEventPublisher(
                $application->make(Dispatcher::class),
                $application->make('db')->connection(),
            ),
        );
        $this->app->singleton(CommercialOfferPersistenceMapper::class);
        $this->app->singleton(
            TrustedCommercialOfferConsumers::class,
            static fn (): TrustedCommercialOfferConsumers => new TrustedCommercialOfferConsumers(
                array_values(array_filter(
                    config('acquisition_offer.trusted_consumers', []),
                    static fn (mixed $consumer): bool => is_string($consumer),
                )),
            ),
        );
        $this->app->singleton(
            CommercialTransactionInterface::class,
            static fn (Application $application): PostgresCommercialTransaction => new PostgresCommercialTransaction(
                $application->make('db')->connection(),
            ),
        );
        $this->app->singleton(
            CommercialOfferRepositoryInterface::class,
            static fn (Application $application): PostgresCommercialOfferRepository => new PostgresCommercialOfferRepository(
                $application->make('db')->connection(),
                $application->make(CommercialOfferPersistenceMapper::class),
            ),
        );

        $this->app->singleton(PlanQueryInterface::class, SubscriptionBillingPlanQueryAdapter::class);
        $this->app->singleton(BillingCycleQueryInterface::class, SubscriptionBillingBillingCycleQueryAdapter::class);
        $this->app->singleton(PricingQueryInterface::class, SubscriptionBillingPricingQueryAdapter::class);
        $this->app->singleton(PlanOfferingQueryInterface::class, SubscriptionBillingPlanOfferingQueryAdapter::class);
        $this->app->singleton(CommercialOfferCheckoutInterface::class, ClaimCommercialOfferService::class);
        $this->app->singleton(
            PrepareRenewalOfferInterface::class,
            static fn (Application $application): PostgresPrepareRenewalOfferAdapter => new PostgresPrepareRenewalOfferAdapter(
                $application->make('db')->connection(),
                $application->make(RenewalCommercialContextReadInterface::class),
                (int) config('acquisition_offer.offer_ttl_minutes', 30),
            ),
        );

        $this->app->when(PrepareCommercialOfferService::class)
            ->needs('$ttlMinutes')
            ->give(static fn (): int => (int) config('acquisition_offer.offer_ttl_minutes', 30));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/acquisition_offer'));

        if ((bool) config('acquisition_offer.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/routes/acquisition_offer.php');
        }
    }
}
