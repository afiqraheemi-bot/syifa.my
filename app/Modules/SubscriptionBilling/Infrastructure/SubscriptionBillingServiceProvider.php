<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure;

use App\Modules\ClinicRegistration\Contracts\Checkout\PublicInitialAcquisitionCheckoutInterface;
use App\Modules\ClinicRegistration\Contracts\Checkout\PublicInitialAcquisitionStatusReadInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ActivatePlanOfferingService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ActivatePlanService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CommercialCatalogueIdentifierGenerator;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CommercialCatalogueIdentifierGeneratorInterface;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreatePlanOfferingService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreatePlanService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\GrandfatherPlanService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\MakePlanUnavailableService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\PlanOfferingAuditTrail;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\RetirePlanOfferingService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\RetirePlanService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\UpdatePlanDetailsService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\UpdatePlanOfferingService;
use App\Modules\SubscriptionBilling\Application\Payment\PaymentApplicationRetryPolicy;
use App\Modules\SubscriptionBilling\Application\Payment\PaymentDataAssembler;
use App\Modules\SubscriptionBilling\Application\Payment\PaymentIdentifierGenerator;
use App\Modules\SubscriptionBilling\Application\Payment\PaymentIdentifierGeneratorInterface;
use App\Modules\SubscriptionBilling\Application\Payment\ProviderVerificationRetryPolicy;
use App\Modules\SubscriptionBilling\Application\Payment\StartPublicInitialAcquisitionCheckoutService;
use App\Modules\SubscriptionBilling\Application\Subscription\AnnualTermCalculator;
use App\Modules\SubscriptionBilling\Application\Subscription\ChangeSubscriptionPlanService;
use App\Modules\SubscriptionBilling\Application\Subscription\ManageSubscriptionRenewalService;
use App\Modules\SubscriptionBilling\Application\Subscription\RenewalCheckoutApplication;
use App\Modules\SubscriptionBilling\Application\Subscription\RenewalOutcomeApplication;
use App\Modules\SubscriptionBilling\Application\Subscription\SubscriptionActivationRetryPolicy;
use App\Modules\SubscriptionBilling\Application\Subscription\SubscriptionTermCalculator;
use App\Modules\SubscriptionBilling\Contracts\Authorization\CommercialCatalogueAuthorizationInterface;
use App\Modules\SubscriptionBilling\Contracts\Authorization\PaymentProviderAdministrationAuthorizationInterface;
use App\Modules\SubscriptionBilling\Contracts\BillingDocument\BillingDocumentReadInterface;
use App\Modules\SubscriptionBilling\Contracts\BillingOverview\BillingOverviewReadInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\BillingOptionCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\CapabilityDefinitionCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\PlanCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\PlanOfferingCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CommercialCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PricingHistoryReadInterface;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\SubscriptionEntitlementLookupInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\InitialAcquisitionCheckoutStoreInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentApplicationJobDispatcherInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentApplicationTransactionInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentAttemptResolverInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentAuditInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentIntegrationOutboxEvent;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentOutboxRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderConfigurationRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderRegistryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentReconciliationCaseRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentTransactionInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentVerificationApplicationRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderVerificationClockInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderVerificationJobDispatcherInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookReceiptRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\CancelAutoRenewInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\ClinicOwnerRenewalCheckoutCommandFactoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\EnableAutoRenewInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\ManualRenewSubscriptionInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\PaymentSessionCreationInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\ProviderHealthInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RenewalCheckoutCommandFactoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RenewalCheckoutStoreInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RenewalCommercialContextReadInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RenewalOutcomeStoreInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\SubscriptionOperationsStoreInterface;
use App\Modules\SubscriptionBilling\Contracts\Repositories\BillingOptionRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Repositories\CapabilityDefinitionRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Repositories\PlanOfferingRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Repositories\PlanRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Repositories\SubscriptionRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\ChangeSubscriptionPlanInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationApplicationRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationAuditInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationEvidenceRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationJobDispatcherInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationReconciliationCaseRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationTransactionInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionIntegrationOutboxRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionSummaryReadInterface;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail\ClinicOwnerSubscriptionDetailReadInterface;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail\PaymentHistoryReadInterface;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail\SubscriptionDetailReadInterface;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail\SubscriptionTimelineReadInterface;
use App\Modules\SubscriptionBilling\Infrastructure\Audit\PaymentAuditAdapter;
use App\Modules\SubscriptionBilling\Infrastructure\Audit\SubscriptionActivationAuditAdapter;
use App\Modules\SubscriptionBilling\Infrastructure\Authorization\CommercialCataloguePlatformAuthorizationAdapter;
use App\Modules\SubscriptionBilling\Infrastructure\Authorization\PaymentProviderAdministrationAuthorization;
use App\Modules\SubscriptionBilling\Infrastructure\CommercialCatalogue\CommercialCatalogueTransactionalService;
use App\Modules\SubscriptionBilling\Infrastructure\Entitlements\PostgresSubscriptionEntitlementLookup;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\ConfiguredProviderHealth;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\LaravelPaymentApplicationJobDispatcher;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\LaravelProviderVerificationJobDispatcher;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\PaymentProviderRegistry;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\PostgresInitialAcquisitionCheckoutStore;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\PostgresPaymentApplicationTransaction;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\PostgresPaymentAttemptResolver;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\PostgresPaymentProviderConfigurationRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\PostgresPaymentTransaction;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\PostgresProviderWebhookReceiptRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\PostgresPublicInitialAcquisitionStatusReadAdapter;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\RegistryPaymentSessionCreator;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\Stripe\StripePaymentProvider;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\SystemProviderVerificationClock;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\ToyyibPay\ToyyibPayPaymentProvider;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\CommercialCataloguePersistenceMapper;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\PaymentPersistenceMapper;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\SubscriptionActivationApplicationPersistenceMapper;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\SubscriptionIntegrationOutboxPersistenceMapper;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\SubscriptionPersistenceMapper;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Queries\PostgresBillingDocumentReadAdapter;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Queries\PostgresBillingOverviewReadAdapter;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Queries\PostgresCommercialCatalogueQueryAdapter;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Queries\PostgresSubscriptionDetailReadAdapter;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Queries\PostgresSubscriptionSummaryReadAdapter;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresBillingOptionRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresCapabilityDefinitionRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresPaymentOutboxRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresPaymentReconciliationCaseRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresPaymentRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresPaymentVerificationApplicationRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresPlanOfferingRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresPlanRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresSubscriptionActivationApplicationRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresSubscriptionActivationReconciliationCaseRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresSubscriptionIntegrationOutboxRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories\PostgresSubscriptionRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Subscription\ApplyRenewalPaymentOutcomeListener;
use App\Modules\SubscriptionBilling\Infrastructure\Subscription\HandleVerifiedPaymentSucceededForSubscriptionActivation;
use App\Modules\SubscriptionBilling\Infrastructure\Subscription\LaravelSubscriptionActivationJobDispatcher;
use App\Modules\SubscriptionBilling\Infrastructure\Subscription\PostgresRenewalCheckoutCommandFactory;
use App\Modules\SubscriptionBilling\Infrastructure\Subscription\PostgresRenewalCheckoutStore;
use App\Modules\SubscriptionBilling\Infrastructure\Subscription\PostgresRenewalOutcomeStore;
use App\Modules\SubscriptionBilling\Infrastructure\Subscription\PostgresSubscriptionActivationEvidenceRepository;
use App\Modules\SubscriptionBilling\Infrastructure\Subscription\PostgresSubscriptionActivationTransaction;
use App\Modules\SubscriptionBilling\Infrastructure\Subscription\PostgresSubscriptionOperationsStore;
use App\Modules\SubscriptionBilling\Presentation\Contracts\ErrorResponseMapperInterface;
use App\Modules\SubscriptionBilling\Presentation\Http\Responses\CommercialCatalogueErrorResponseMapper;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class SubscriptionBillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            SubscriptionEntitlementLookupInterface::class,
            static fn (Application $application): PostgresSubscriptionEntitlementLookup => new PostgresSubscriptionEntitlementLookup(
                $application->make('db')->connection(),
            ),
        );
        $this->app->singleton(
            BillingOverviewReadInterface::class,
            static fn (Application $application): PostgresBillingOverviewReadAdapter => new PostgresBillingOverviewReadAdapter(
                $application->make('db')->connection(),
            ),
        );
        $this->app->singleton(
            BillingDocumentReadInterface::class,
            static fn (Application $application): PostgresBillingDocumentReadAdapter => new PostgresBillingDocumentReadAdapter(
                $application->make('db')->connection(),
            ),
        );
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
        $this->app->alias(PostgresCommercialCatalogueQueryAdapter::class, PricingHistoryReadInterface::class);

        $this->app->singleton(
            ConnectionInterface::class,
            static function (Application $application): ConnectionInterface {
                return $application->make('db')->connection();
            },
        );

        $this->app->singleton(PaymentDataAssembler::class);
        $this->app->singleton(PaymentIdentifierGeneratorInterface::class, PaymentIdentifierGenerator::class);
        $this->app->singleton(PaymentPersistenceMapper::class);
        $this->app->singleton(SubscriptionPersistenceMapper::class);
        $this->app->singleton(
            SubscriptionSummaryReadInterface::class,
            static fn (Application $application): PostgresSubscriptionSummaryReadAdapter => new PostgresSubscriptionSummaryReadAdapter(
                $application->make('db')->connection(),
            ),
        );
        $this->app->singleton(
            PostgresSubscriptionDetailReadAdapter::class,
            static fn (Application $application): PostgresSubscriptionDetailReadAdapter => new PostgresSubscriptionDetailReadAdapter(
                $application->make('db')->connection(),
            ),
        );
        $this->app->alias(PostgresSubscriptionDetailReadAdapter::class, SubscriptionDetailReadInterface::class);
        $this->app->alias(PostgresSubscriptionDetailReadAdapter::class, ClinicOwnerSubscriptionDetailReadInterface::class);
        $this->app->alias(PostgresSubscriptionDetailReadAdapter::class, SubscriptionTimelineReadInterface::class);
        $this->app->alias(PostgresSubscriptionDetailReadAdapter::class, PaymentHistoryReadInterface::class);
        $this->app->alias(PostgresSubscriptionDetailReadAdapter::class, RenewalCommercialContextReadInterface::class);
        $this->app->singleton(
            SubscriptionOperationsStoreInterface::class,
            static fn (Application $application): PostgresSubscriptionOperationsStore => new PostgresSubscriptionOperationsStore(
                $application->make('db')->connection(),
            ),
        );
        $this->app->singleton(ManageSubscriptionRenewalService::class);
        $this->app->alias(ManageSubscriptionRenewalService::class, ManualRenewSubscriptionInterface::class);
        $this->app->alias(ManageSubscriptionRenewalService::class, EnableAutoRenewInterface::class);
        $this->app->alias(ManageSubscriptionRenewalService::class, CancelAutoRenewInterface::class);
        $this->app->singleton(ChangeSubscriptionPlanInterface::class, ChangeSubscriptionPlanService::class);
        $this->app->singleton(RenewalCheckoutStoreInterface::class, PostgresRenewalCheckoutStore::class);
        $this->app->singleton(RenewalOutcomeStoreInterface::class, PostgresRenewalOutcomeStore::class);
        $this->app->singleton(PostgresRenewalCheckoutCommandFactory::class);
        $this->app->alias(PostgresRenewalCheckoutCommandFactory::class, RenewalCheckoutCommandFactoryInterface::class);
        $this->app->alias(PostgresRenewalCheckoutCommandFactory::class, ClinicOwnerRenewalCheckoutCommandFactoryInterface::class);
        $this->app->singleton(PaymentSessionCreationInterface::class, RegistryPaymentSessionCreator::class);
        $this->app->singleton(
            InitialAcquisitionCheckoutStoreInterface::class,
            PostgresInitialAcquisitionCheckoutStore::class,
        );
        $this->app->singleton(
            PublicInitialAcquisitionCheckoutInterface::class,
            StartPublicInitialAcquisitionCheckoutService::class,
        );
        $this->app->singleton(
            PublicInitialAcquisitionStatusReadInterface::class,
            PostgresPublicInitialAcquisitionStatusReadAdapter::class,
        );
        $this->app->singleton(RenewalCheckoutApplication::class);
        $this->app->singleton(RenewalOutcomeApplication::class);
        $this->app->singleton(SubscriptionIntegrationOutboxPersistenceMapper::class);
        $this->app->singleton(SubscriptionActivationApplicationPersistenceMapper::class);
        $this->app->singleton(AnnualTermCalculator::class);
        $this->app->singleton(SubscriptionTermCalculator::class);
        $this->app->singleton(SubscriptionActivationRetryPolicy::class);
        $this->app->singleton(SubscriptionActivationAuditInterface::class, SubscriptionActivationAuditAdapter::class);
        $this->app->singleton(SubscriptionActivationTransactionInterface::class, PostgresSubscriptionActivationTransaction::class);
        $this->app->singleton(SubscriptionActivationEvidenceRepositoryInterface::class, PostgresSubscriptionActivationEvidenceRepository::class);
        $this->app->singleton(SubscriptionActivationApplicationRepositoryInterface::class, PostgresSubscriptionActivationApplicationRepository::class);
        $this->app->singleton(SubscriptionActivationReconciliationCaseRepositoryInterface::class, PostgresSubscriptionActivationReconciliationCaseRepository::class);
        $this->app->singleton(SubscriptionIntegrationOutboxRepositoryInterface::class, PostgresSubscriptionIntegrationOutboxRepository::class);
        $this->app->singleton(SubscriptionActivationJobDispatcherInterface::class, LaravelSubscriptionActivationJobDispatcher::class);
        $this->app->singleton(PaymentAuditInterface::class, PaymentAuditAdapter::class);
        $this->app->singleton(PaymentApplicationRetryPolicy::class, static fn (): PaymentApplicationRetryPolicy => new PaymentApplicationRetryPolicy(
            (int) config('payment_providers.application.lease_seconds', 120),
            (int) config('payment_providers.application.max_attempts', 5),
            (int) config('payment_providers.application.base_delay_seconds', 5),
            (int) config('payment_providers.application.max_delay_seconds', 120),
        ));
        $this->app->singleton(ProviderVerificationJobDispatcherInterface::class, LaravelProviderVerificationJobDispatcher::class);
        $this->app->singleton(PaymentApplicationJobDispatcherInterface::class, LaravelPaymentApplicationJobDispatcher::class);
        $this->app->singleton(PaymentApplicationTransactionInterface::class, PostgresPaymentApplicationTransaction::class);
        $this->app->singleton(PaymentVerificationApplicationRepositoryInterface::class, PostgresPaymentVerificationApplicationRepository::class);
        $this->app->singleton(PaymentReconciliationCaseRepositoryInterface::class, PostgresPaymentReconciliationCaseRepository::class);
        $this->app->singleton(PaymentOutboxRepositoryInterface::class, PostgresPaymentOutboxRepository::class);
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
            ProviderHealthInterface::class,
            static fn (Application $application): ConfiguredProviderHealth => new ConfiguredProviderHealth(
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
            SubscriptionRepositoryInterface::class,
            static fn (Application $application): PostgresSubscriptionRepository => new PostgresSubscriptionRepository(
                $application->make('db')->connection(),
                $application->make(SubscriptionPersistenceMapper::class),
            ),
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
            CommercialCatalogueIdentifierGeneratorInterface::class,
            CommercialCatalogueIdentifierGenerator::class,
        );
        $this->app->singleton(PlanOfferingAuditTrail::class);
        foreach ([
            CreatePlanOfferingService::class,
            UpdatePlanOfferingService::class,
            ActivatePlanOfferingService::class,
            RetirePlanOfferingService::class,
        ] as $serviceClass) {
            $this->app->singleton(
                $serviceClass,
                static function (Application $application) use ($serviceClass): CommercialCatalogueTransactionalService {
                    $service = match ($serviceClass) {
                        CreatePlanOfferingService::class => new CreatePlanOfferingService(
                            $application->make(CommercialCatalogueIdentifierGeneratorInterface::class),
                            $application->make(PlanOfferingRepositoryInterface::class),
                            $application->make(PlanOfferingAuditTrail::class),
                        ),
                        UpdatePlanOfferingService::class => new UpdatePlanOfferingService(
                            $application->make(PlanOfferingRepositoryInterface::class),
                            $application->make(PlanOfferingAuditTrail::class),
                        ),
                        ActivatePlanOfferingService::class => new ActivatePlanOfferingService(
                            $application->make(PlanOfferingRepositoryInterface::class),
                            $application->make(PlanOfferingAuditTrail::class),
                        ),
                        RetirePlanOfferingService::class => new RetirePlanOfferingService(
                            $application->make(PlanOfferingRepositoryInterface::class),
                            $application->make(PlanOfferingAuditTrail::class),
                        ),
                    };

                    return new CommercialCatalogueTransactionalService(
                        $application->make(ConnectionInterface::class),
                        [$service, 'execute'],
                    );
                },
            );
        }

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
        Event::listen(PaymentIntegrationOutboxEvent::class, ApplyRenewalPaymentOutcomeListener::class);
        Event::listen(PaymentIntegrationOutboxEvent::class, HandleVerifiedPaymentSucceededForSubscriptionActivation::class);
    }
}
