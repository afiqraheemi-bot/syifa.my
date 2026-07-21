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
use App\Modules\SubscriptionBilling\Application\Payment\ProviderVerificationRetryPolicy;
use App\Modules\SubscriptionBilling\Contracts\Authorization\CommercialCatalogueAuthorizationInterface;
use App\Modules\SubscriptionBilling\Contracts\Authorization\PaymentProviderAdministrationAuthorizationInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\BillingOptionCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\CapabilityDefinitionCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\PlanCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\PlanOfferingCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CommercialCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentAttemptResolverInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentAuditInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderConfigurationRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderRegistryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentTransactionInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderVerificationClockInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderVerificationJobDispatcherInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookReceiptRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Repositories\BillingOptionRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Repositories\CapabilityDefinitionRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Repositories\PlanOfferingRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Repositories\PlanRepositoryInterface;
use App\Modules\SubscriptionBilling\Infrastructure\Audit\PaymentAuditAdapter;
use App\Modules\SubscriptionBilling\Infrastructure\Authorization\CommercialCataloguePlatformAuthorizationAdapter;
use App\Modules\SubscriptionBilling\Infrastructure\Authorization\PaymentProviderAdministrationAuthorization;
use App\Modules\SubscriptionBilling\Infrastructure\CommercialCatalogue\CommercialCatalogueTransactionalService;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\LaravelProviderVerificationJobDispatcher;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\PaymentProviderRegistry;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\PostgresPaymentAttemptResolver;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\PostgresPaymentProviderConfigurationRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\PostgresPaymentTransaction;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\PostgresProviderWebhookReceiptRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\Stripe\StripePaymentProvider;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\SystemProviderVerificationClock;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\ToyyibPay\ToyyibPayPaymentProvider;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\CommercialCataloguePersistenceMapper;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\PaymentPersistenceMapper;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Queries\PostgresCommercialCatalogueQueryAdapter;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresBillingOptionRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresCapabilityDefinitionRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresPaymentRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresPlanOfferingRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresPlanRepository;
use App\Modules\SubscriptionBilling\Presentation\Contracts\ErrorResponseMapperInterface;
use App\Modules\SubscriptionBilling\Presentation\Http\Responses\CommercialCatalogueErrorResponseMapper;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

final class SubscriptionBillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('payment_providers.php'), 'payment_providers');
        $this->app->singleton(
            CommercialCatalogueAuthorizationInterface::class,
            CommercialCataloguePlatformAuthorizationAdapter::class,
        );
        $this->app->singleton(
            PaymentProviderAdministrationAuthorizationInterface::class,
            PaymentProviderAdministrationAuthorization::class,
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
        $this->app->singleton(ProviderVerificationJobDispatcherInterface::class, LaravelProviderVerificationJobDispatcher::class);
        $this->app->singleton(ProviderVerificationClockInterface::class, SystemProviderVerificationClock::class);
        $this->app->singleton(ProviderVerificationRetryPolicy::class, static fn (): ProviderVerificationRetryPolicy => new ProviderVerificationRetryPolicy(
            (int) config('payment_providers.verification.lease_seconds', 300),
            (int) config('payment_providers.verification.transport_max_attempts', 8),
            (int) config('payment_providers.verification.malformed_max_attempts', 2),
            (int) config('payment_providers.verification.base_delay_seconds', 30),
            (int) config('payment_providers.verification.max_delay_seconds', 1800),
            (int) config('payment_providers.verification.max_retry_after_seconds', 21600),
        ));
        $this->app->singleton(StripePaymentProvider::class, static fn (Application $application): StripePaymentProvider => new StripePaymentProvider(
            $application->make(HttpFactory::class),
            (string) config('payment_providers.stripe.secret_key', ''),
            (string) config('payment_providers.stripe.webhook_secret', ''),
            (string) config('payment_providers.stripe.success_url', ''),
            (string) config('payment_providers.stripe.cancel_url', ''),
            (string) config('payment_providers.stripe.base_url', 'https://api.stripe.com/v1'),
        ));
        $this->app->singleton(ToyyibPayPaymentProvider::class, static fn (Application $application): ToyyibPayPaymentProvider => new ToyyibPayPaymentProvider(
            $application->make(HttpFactory::class),
            (string) config('payment_providers.toyyibpay.secret_key', ''),
            (string) config('payment_providers.toyyibpay.category_code', ''),
            (string) config('payment_providers.toyyibpay.return_url', ''),
            (string) config('payment_providers.toyyibpay.callback_url', ''),
            (string) config('payment_providers.toyyibpay.base_url', 'https://toyyibpay.com'),
        ));
        $this->app->singleton(
            PaymentProviderConfigurationRepositoryInterface::class,
            static fn (Application $application): PostgresPaymentProviderConfigurationRepository => new PostgresPaymentProviderConfigurationRepository(
                $application->make('db')->connection(),
            ),
        );
        $this->app->singleton(
            PaymentProviderRegistryInterface::class,
            static fn (Application $application): PaymentProviderRegistry => new PaymentProviderRegistry(
                [$application->make(StripePaymentProvider::class), $application->make(ToyyibPayPaymentProvider::class)],
                $application->make(PaymentProviderConfigurationRepositoryInterface::class),
            ),
        );
        $this->app->singleton(
            PaymentTransactionInterface::class,
            static fn (Application $application): PostgresPaymentTransaction => new PostgresPaymentTransaction(
                $application->make('db')->connection(),
            ),
        );
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
            ProviderWebhookReceiptRepositoryInterface::class,
            static fn (Application $application): PostgresProviderWebhookReceiptRepository => new PostgresProviderWebhookReceiptRepository(
                $application->make('db')->connection(),
            ),
        );
        $this->app->singleton(
            PaymentAttemptResolverInterface::class,
            static fn (Application $application): PostgresPaymentAttemptResolver => new PostgresPaymentAttemptResolver(
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
        $this->loadRoutesFrom(__DIR__.'/routes/payment_providers.php');
    }
}
