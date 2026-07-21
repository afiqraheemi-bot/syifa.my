<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure;

use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ActivatePlanService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CommercialCatalogueIdentifierGeneratorInterface;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreatePlanService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\GrandfatherPlanService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\MakePlanUnavailableService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\RetirePlanService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\UpdatePlanDetailsService;
use App\Modules\SubscriptionBilling\Application\Payment\PaymentDataAssembler;
use App\Modules\SubscriptionBilling\Application\Payment\PaymentIdentifierGenerator;
use App\Modules\SubscriptionBilling\Application\Payment\PaymentIdentifierGeneratorInterface;
use App\Modules\SubscriptionBilling\Contracts\Authorization\CommercialCatalogueAuthorizationInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\BillingOptionCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\CapabilityDefinitionCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\PlanCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\PlanOfferingCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CommercialCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentAuditInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\WebhookReceiptRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Repositories\BillingOptionRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Repositories\CapabilityDefinitionRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Repositories\PlanOfferingRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Repositories\PlanRepositoryInterface;
use App\Modules\SubscriptionBilling\Infrastructure\Audit\PaymentAuditAdapter;
use App\Modules\SubscriptionBilling\Infrastructure\Authorization\CommercialCataloguePlatformAuthorizationAdapter;
use App\Modules\SubscriptionBilling\Infrastructure\CommercialCatalogue\CommercialCatalogueTransactionalService;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\UnavailablePaymentProvider;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\CommercialCataloguePersistenceMapper;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\PaymentPersistenceMapper;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Queries\PostgresCommercialCatalogueQueryAdapter;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresBillingOptionRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresCapabilityDefinitionRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresPaymentRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresPlanOfferingRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresPlanRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresWebhookReceiptRepository;
use App\Modules\SubscriptionBilling\Presentation\Contracts\ErrorResponseMapperInterface;
use App\Modules\SubscriptionBilling\Presentation\Http\Responses\CommercialCatalogueErrorResponseMapper;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\ServiceProvider;

final class SubscriptionBillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            CommercialCatalogueAuthorizationInterface::class,
            CommercialCataloguePlatformAuthorizationAdapter::class,
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

        $this->app->singleton(
            ConnectionInterface::class,
            static function (Application $application): ConnectionInterface {
                return $application->make('db')->connection();
            },
        );

        $this->app->singleton(PaymentDataAssembler::class);
        $this->app->singleton(PaymentIdentifierGeneratorInterface::class, PaymentIdentifierGenerator::class);
        $this->app->singleton(PaymentPersistenceMapper::class);
        $this->app->singleton(PaymentAuditInterface::class, PaymentAuditAdapter::class);
        $this->app->singleton(PaymentProviderInterface::class, UnavailablePaymentProvider::class);
        $this->app->singleton(
            PaymentRepositoryInterface::class,
            static function (Application $application): PostgresPaymentRepository {
                return new PostgresPaymentRepository(
                    $application->make('db')->connection(),
                    $application->make(PaymentPersistenceMapper::class),
                );
            },
        );
        $this->app->singleton(
            WebhookReceiptRepositoryInterface::class,
            static fn (Application $application): PostgresWebhookReceiptRepository => new PostgresWebhookReceiptRepository(
                $application->make('db')->connection(),
            ),
        );

        $this->app->singleton(
            PlanRepositoryInterface::class,
            static function (Application $application): PostgresPlanRepository {
                return new PostgresPlanRepository(
                    $application->make('db')->connection(),
                    new CommercialCataloguePersistenceMapper,
                );
            },
        );

        $this->app->singleton(
            BillingOptionRepositoryInterface::class,
            static function (Application $application): PostgresBillingOptionRepository {
                return new PostgresBillingOptionRepository(
                    $application->make('db')->connection(),
                    new CommercialCataloguePersistenceMapper,
                );
            },
        );

        $this->app->singleton(
            CapabilityDefinitionRepositoryInterface::class,
            static function (Application $application): PostgresCapabilityDefinitionRepository {
                return new PostgresCapabilityDefinitionRepository(
                    $application->make('db')->connection(),
                    new CommercialCataloguePersistenceMapper,
                );
            },
        );

        $this->app->singleton(
            PlanOfferingRepositoryInterface::class,
            static function (Application $application): PostgresPlanOfferingRepository {
                return new PostgresPlanOfferingRepository(
                    $application->make('db')->connection(),
                    new CommercialCataloguePersistenceMapper,
                );
            },
        );

        $this->app->singleton(
            CreatePlanService::class,
            static function (Application $application): CommercialCatalogueTransactionalService {
                return new CommercialCatalogueTransactionalService(
                    $application->make(ConnectionInterface::class),
                    [
                        new CreatePlanService(
                            $application->make(CommercialCatalogueIdentifierGeneratorInterface::class),
                            $application->make(PlanRepositoryInterface::class),
                            $application->make(AuditEntryRecorderInterface::class),
                        ),
                        'execute',
                    ],
                );
            },
        );

        $this->app->singleton(
            UpdatePlanDetailsService::class,
            static function (Application $application): CommercialCatalogueTransactionalService {
                return new CommercialCatalogueTransactionalService(
                    $application->make(ConnectionInterface::class),
                    [
                        new UpdatePlanDetailsService(
                            $application->make(PlanRepositoryInterface::class),
                            $application->make(AuditEntryRecorderInterface::class),
                        ),
                        'execute',
                    ],
                );
            },
        );

        $this->app->singleton(
            ActivatePlanService::class,
            static function (Application $application): CommercialCatalogueTransactionalService {
                return new CommercialCatalogueTransactionalService(
                    $application->make(ConnectionInterface::class),
                    [
                        new ActivatePlanService(
                            $application->make(PlanRepositoryInterface::class),
                            $application->make(AuditEntryRecorderInterface::class),
                        ),
                        'execute',
                    ],
                );
            },
        );

        $this->app->singleton(
            MakePlanUnavailableService::class,
            static function (Application $application): CommercialCatalogueTransactionalService {
                return new CommercialCatalogueTransactionalService(
                    $application->make(ConnectionInterface::class),
                    [
                        new MakePlanUnavailableService(
                            $application->make(PlanRepositoryInterface::class),
                            $application->make(AuditEntryRecorderInterface::class),
                        ),
                        'execute',
                    ],
                );
            },
        );

        $this->app->singleton(
            GrandfatherPlanService::class,
            static function (Application $application): CommercialCatalogueTransactionalService {
                return new CommercialCatalogueTransactionalService(
                    $application->make(ConnectionInterface::class),
                    [
                        new GrandfatherPlanService(
                            $application->make(PlanRepositoryInterface::class),
                            $application->make(AuditEntryRecorderInterface::class),
                        ),
                        'execute',
                    ],
                );
            },
        );

        $this->app->singleton(
            RetirePlanService::class,
            static function (Application $application): CommercialCatalogueTransactionalService {
                return new CommercialCatalogueTransactionalService(
                    $application->make(ConnectionInterface::class),
                    [
                        new RetirePlanService(
                            $application->make(PlanRepositoryInterface::class),
                            $application->make(AuditEntryRecorderInterface::class),
                        ),
                        'execute',
                    ],
                );
            },
        );

    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/subscription_billing'));
    }
}
