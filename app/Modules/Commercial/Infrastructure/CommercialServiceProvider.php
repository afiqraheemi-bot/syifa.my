<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Infrastructure;

use App\Modules\Commercial\Application\ClaimCommercialOfferService;
use App\Modules\Commercial\Application\CommercialOfferDataAssembler;
use App\Modules\Commercial\Application\CommercialOfferIdentifierGenerator;
use App\Modules\Commercial\Application\CommercialOfferIdentifierGeneratorInterface;
use App\Modules\Commercial\Application\PrepareCommercialOfferService;
use App\Modules\Commercial\Application\TrustedCommercialOfferConsumers;
use App\Modules\Commercial\Contracts\Checkout\CommercialOfferCheckoutInterface;
use App\Modules\Commercial\Contracts\Events\CommercialOfferEventPublisherInterface;
use App\Modules\Commercial\Contracts\ReferenceData\BillingCycleQueryInterface;
use App\Modules\Commercial\Contracts\ReferenceData\PlanOfferingQueryInterface;
use App\Modules\Commercial\Contracts\ReferenceData\PlanQueryInterface;
use App\Modules\Commercial\Contracts\ReferenceData\PricingQueryInterface;
use App\Modules\Commercial\Contracts\Repositories\CommercialOfferRepositoryInterface;
use App\Modules\Commercial\Contracts\Transactions\CommercialTransactionInterface;
use App\Modules\Commercial\Infrastructure\Events\LaravelCommercialOfferEventPublisher;
use App\Modules\Commercial\Infrastructure\Persistence\Mappers\CommercialOfferPersistenceMapper;
use App\Modules\Commercial\Infrastructure\Persistence\Repositories\PostgresCommercialOfferRepository;
use App\Modules\Commercial\Infrastructure\ReferenceData\SubscriptionBillingBillingCycleQueryAdapter;
use App\Modules\Commercial\Infrastructure\ReferenceData\SubscriptionBillingPlanOfferingQueryAdapter;
use App\Modules\Commercial\Infrastructure\ReferenceData\SubscriptionBillingPlanQueryAdapter;
use App\Modules\Commercial\Infrastructure\ReferenceData\SubscriptionBillingPricingQueryAdapter;
use App\Modules\Commercial\Infrastructure\Transactions\PostgresCommercialTransaction;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class CommercialServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('commercial.php'), 'commercial');

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
                    config('commercial.trusted_consumers', []),
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

        $this->app->when(PrepareCommercialOfferService::class)
            ->needs('$ttlMinutes')
            ->give(static fn (): int => (int) config('commercial.offer_ttl_minutes', 30));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/commercial'));

        if ((bool) config('commercial.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/routes/commercial.php');
        }
    }
}
