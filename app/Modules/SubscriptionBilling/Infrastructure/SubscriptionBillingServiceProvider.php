<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure;

use App\Modules\SubscriptionBilling\Contracts\Authorization\CommercialCatalogueAuthorizationInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\BillingOptionCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\CapabilityDefinitionCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\PlanCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\PlanOfferingCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CommercialCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Infrastructure\Authorization\DenyAllCommercialCatalogueAuthorization;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Queries\PostgresCommercialCatalogueQueryAdapter;
use App\Modules\SubscriptionBilling\Presentation\Contracts\ErrorResponseMapperInterface;
use App\Modules\SubscriptionBilling\Presentation\Http\Responses\CommercialCatalogueErrorResponseMapper;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class SubscriptionBillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            CommercialCatalogueAuthorizationInterface::class,
            DenyAllCommercialCatalogueAuthorization::class,
        );

        $this->app->singleton(
            ErrorResponseMapperInterface::class,
            CommercialCatalogueErrorResponseMapper::class,
        );

        $this->app->singleton(
            PostgresCommercialCatalogueQueryAdapter::class,
            static function (Application $application): PostgresCommercialCatalogueQueryAdapter {
                $database = $application->make('db');

                return new PostgresCommercialCatalogueQueryAdapter($database->connection());
            },
        );

        $this->app->alias(PostgresCommercialCatalogueQueryAdapter::class, CommercialCatalogueQueryInterface::class);
        $this->app->alias(PostgresCommercialCatalogueQueryAdapter::class, PlanCatalogueQueryInterface::class);
        $this->app->alias(PostgresCommercialCatalogueQueryAdapter::class, BillingOptionCatalogueQueryInterface::class);
        $this->app->alias(PostgresCommercialCatalogueQueryAdapter::class, CapabilityDefinitionCatalogueQueryInterface::class);
        $this->app->alias(PostgresCommercialCatalogueQueryAdapter::class, PlanOfferingCatalogueQueryInterface::class);
    }
}
