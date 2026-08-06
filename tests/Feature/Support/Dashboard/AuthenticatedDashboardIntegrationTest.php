<?php

declare(strict_types=1);

namespace Tests\Feature\Support\Dashboard;

use App\Modules\Booking\Contracts\Operations\ClinicOwnerBookingOperationsInterface;
use App\Modules\Booking\Contracts\Queries\BookingDetailData;
use App\Modules\Booking\Contracts\Queries\BookingHistoryData;
use App\Modules\Booking\Contracts\Queries\ClinicOwnerBookingReadInterface;
use App\Modules\Booking\Contracts\Queries\PublicBookingFormReaderData;
use App\Modules\Booking\Contracts\Queries\PublicBookingFormReaderInterface;
use App\Modules\Booking\Contracts\Queries\PublicBookingFormServiceData;
use App\Modules\Booking\Contracts\Repositories\BookingFormConfigurationRepositoryInterface;
use App\Modules\Booking\Contracts\Repositories\ServiceRepositoryInterface;
use App\Modules\Booking\Domain\BookingFormConfiguration;
use App\Modules\Booking\Domain\Service;
use App\Modules\Booking\Domain\ValueObjects\BookingFormField;
use App\Modules\Booking\Domain\ValueObjects\FieldLabels;
use App\Modules\Booking\Domain\ValueObjects\FieldOrder;
use App\Modules\Booking\Domain\ValueObjects\RequiredFields;
use App\Modules\Booking\Domain\ValueObjects\ServiceId as BookingServiceId;
use App\Modules\Booking\Domain\ValueObjects\ServiceName;
use App\Modules\Booking\Domain\ValueObjects\SortOrder;
use App\Modules\Booking\Domain\ValueObjects\TenantId as BookingTenantId;
use App\Modules\ClinicRegistration\Contracts\Review\ClinicRegistrationReviewReadInterface;
use App\Modules\ClinicRegistration\Contracts\Review\RegistrationReviewItemData;
use App\Modules\Notification\Contracts\NotificationReadInterface;
use App\Modules\Onboarding\Contracts\Administration\OnboardingAuditInterface;
use App\Modules\Onboarding\Contracts\Administration\SuperAdminOnboardingReadInterface;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerDashboardData;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerDashboardReadInterface;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerJobDetailData;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerQueueJobData;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerRecentAssignmentData;
use App\Modules\Onboarding\Contracts\LaunchReadiness\LaunchReadinessData;
use App\Modules\Onboarding\Contracts\LaunchReadiness\LaunchReadinessReadInterface;
use App\Modules\Onboarding\Contracts\Tasks\OnboardingTaskReadInterface;
use App\Modules\Onboarding\Contracts\WebsiteApproval\ClinicOwnerWebsiteApprovalReadInterface;
use App\Modules\Onboarding\Contracts\WebsiteApproval\OnboardingWorkflowTransactionInterface;
use App\Modules\Onboarding\Contracts\WebsiteApproval\WebsiteApprovalAuditInterface;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Entities\OnboardingTask;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\OnboardingJob;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Repositories\OnboardingJobRepositoryInterface;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingJobId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingTaskId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingTaskResponsibility;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingTaskStatus;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\PlatformIdentityId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\TenantId as OnboardingTenantId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteDesignerAssignmentId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteId as OnboardingWebsiteId;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryReadInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\Dashboard\PlatformDashboardActivityData;
use App\Modules\PlatformAdministration\Contracts\Dashboard\PlatformDashboardData;
use App\Modules\PlatformAdministration\Contracts\Dashboard\PlatformDashboardReadInterface;
use App\Modules\PlatformAdministration\Domain\AuditEntry\AuditEntry;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditEntryId;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use App\Modules\ReportingAnalytics\Contracts\ReportReadInterface;
use App\Modules\SubscriptionBilling\Contracts\BillingOverview\BillingOverviewData;
use App\Modules\SubscriptionBilling\Contracts\BillingOverview\BillingOverviewReadInterface;
use App\Modules\SubscriptionBilling\Contracts\BillingOverview\RecentPaymentData;
use App\Modules\SubscriptionBilling\Contracts\BillingOverview\SubscriptionOverviewData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\BillingOptionCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\CapabilityDefinitionCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\PlanCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\PlanOfferingCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\BillingOptionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CapabilityDefinitionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CommercialCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\OffsetPaginationInput;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\OffsetPaginationMeta;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\PaginatedBillingOptionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\PaginatedCapabilityDefinitionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\PaginatedPlanData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\PaginatedPlanOfferingData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PlanData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PlanOfferingData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PricingHistoryData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PricingHistoryReadInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\AutoRenewCommand;
use App\Modules\SubscriptionBilling\Contracts\Renewal\AutoRenewOperationResult;
use App\Modules\SubscriptionBilling\Contracts\Renewal\CancelAutoRenewInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\EnableAutoRenewInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\ManualRenewSubscriptionCommand;
use App\Modules\SubscriptionBilling\Contracts\Renewal\ManualRenewSubscriptionInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RenewalOperationResult;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionSummaryData;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionSummaryReadInterface;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail\PaymentHistoryReadInterface;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail\SubscriptionDetailData;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail\SubscriptionDetailReadInterface;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail\SubscriptionPaymentData;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail\SubscriptionTimelineData;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail\SubscriptionTimelineReadInterface;
use App\Modules\TenantManagement\Contracts\TenantOverview\TenantOverviewData;
use App\Modules\TenantManagement\Contracts\TenantOverview\TenantOverviewReadInterface;
use App\Modules\WebsiteBuilder\Application\ClinicBooking\ClinicBookingDateOverrideData;
use App\Modules\WebsiteBuilder\Contracts\Assets\WebsiteAssetBinaryStorageInterface;
use App\Modules\WebsiteBuilder\Contracts\CustomDomain\CustomDomainRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingFormConfiguration;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingFormConfigurationReaderInterface;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingServiceOption;
use App\Modules\WebsiteBuilder\Contracts\PublicAddress\WebsitePublicAddressData;
use App\Modules\WebsiteBuilder\Contracts\PublicAddress\WebsitePublicAddressReadInterface;
use App\Modules\WebsiteBuilder\Contracts\PublicAddress\WebsitePublicAddressRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Publication\WebsitePublicationApprovalReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Queries\ClinicSummaryData;
use App\Modules\WebsiteBuilder\Contracts\Queries\ClinicSummaryReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Queries\PublishedWebsiteSectionSummaryData;
use App\Modules\WebsiteBuilder\Contracts\Queries\PublishedWebsiteSnapshotData;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteDetailData;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsitePublishedSnapshotReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteSeoSummaryData;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteSeoSummaryReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteSummaryData;
use App\Modules\WebsiteBuilder\Contracts\Repositories\ClinicBookingDateOverrideRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\ClinicRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteDraftRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Transactions\ClinicTransactionInterface;
use App\Modules\WebsiteBuilder\Contracts\Transactions\WebsitePublicationTransactionInterface;
use App\Modules\WebsiteBuilder\Domain\Clinic;
use App\Modules\WebsiteBuilder\Domain\CustomDomain\CustomDomain;
use App\Modules\WebsiteBuilder\Domain\Exceptions\StaleClinicWriteException;
use App\Modules\WebsiteBuilder\Domain\SectionContent\AboutSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\BookingCtaSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\ContactSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\DoctorsSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\FaqSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\GallerySectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\HeroSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\ServicesSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\TestimonialsSectionContent;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\ClinicContactProfile;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\ClinicId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\IanaTimezone;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\LocalTime;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\OpeningInterval;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteBranding;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WeeklyOperatingHours;
use App\Modules\WebsiteBuilder\Domain\Website;
use App\Modules\WebsiteBuilder\Domain\WebsiteDraftContent;
use App\Support\Authorization\Application\AuthorizationService;
use App\Support\Identity\ActorType;
use App\Support\Identity\AuthenticatedIdentity;
use App\Support\Identity\AuthenticatedIdentityInterface;
use App\Support\Identity\CurrentUserInterface;
use App\Support\Identity\PermissionResolverInterface;
use App\Support\Identity\RoleResolverInterface;
use DateTimeImmutable;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class AuthenticatedDashboardIntegrationTest extends TestCase
{
    private DashboardRecordedBookingOperations $bookingOperations;

    private DashboardFixedWebsiteRepository $websiteRepository;

    private DashboardFixedWebsiteDraftRepository $websiteDraftRepository;

    private DashboardWebsitePublicAddressRepository $websiteAddresses;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(ClinicSummaryReadInterface::class, new DashboardFixedClinicSummary);
        $this->app->instance(SubscriptionSummaryReadInterface::class, new DashboardFixedSubscriptionSummary);
        $this->app->instance(WebsiteReadInterface::class, new DashboardFixedWebsiteRead);
        $this->app->instance(CustomDomainRepositoryInterface::class, new DashboardEmptyCustomDomainRepository);
        $this->app->instance(WebsitePublishedSnapshotReadInterface::class, new DashboardFixedWebsiteSnapshot);
        $this->app->instance(WebsiteSeoSummaryReadInterface::class, new DashboardFixedSeoSummary);
        $this->websiteRepository = new DashboardFixedWebsiteRepository;
        $this->app->instance(WebsiteRepositoryInterface::class, $this->websiteRepository);
        $this->websiteDraftRepository = new DashboardFixedWebsiteDraftRepository;
        $this->app->instance(
            WebsiteDraftRepositoryInterface::class,
            $this->websiteDraftRepository,
        );
        $this->app->instance(
            PublicBookingFormConfigurationReaderInterface::class,
            new DashboardFixedPublicBookingConfiguration,
        );
        $this->app->instance(ClinicRepositoryInterface::class, new DashboardFixedClinicRepository);
        $this->app->instance(ClinicBookingDateOverrideRepositoryInterface::class, new DashboardClinicBookingDateOverrides);
        $this->websiteAddresses = new DashboardWebsitePublicAddressRepository;
        $this->app->instance(
            WebsitePublicAddressRepositoryInterface::class,
            $this->websiteAddresses,
        );
        $this->app->instance(
            WebsitePublicAddressReadInterface::class,
            $this->websiteAddresses,
        );
        $this->app->instance(ClinicTransactionInterface::class, new DashboardClinicTransaction);
        $this->app->instance(
            WebsitePublicationTransactionInterface::class,
            new DashboardWebsitePublicationTransaction,
        );
        $this->app->instance(AuditEntryRecorderInterface::class, new DashboardAuditRecorder);
        $this->app->instance(AuditEntryReadInterface::class, new DashboardFixedAuditRead);
        $this->app->instance(NotificationReadInterface::class, new DashboardFixedNotificationRead);
        $this->app->instance(ReportReadInterface::class, new DashboardFixedReportRead);
        $this->app->instance(
            BookingFormConfigurationRepositoryInterface::class,
            new DashboardFixedBookingConfigurationRepository,
        );
        $this->app->instance(ServiceRepositoryInterface::class, new DashboardFixedBookingServiceRepository);
        $this->app->instance(ClinicOwnerBookingReadInterface::class, new DashboardFixedBookingRead);
        $this->app->instance(PublicBookingFormReaderInterface::class, new DashboardFixedBookingFormRead);
        $this->app->instance(WebsiteDesignerDashboardReadInterface::class, new DashboardFixedDesignerRead);
        $onboardingJob = OnboardingJob::create(
            new OnboardingJobId('00000000-0000-4000-8000-000000000101'),
            new OnboardingTenantId('00000000-0000-4000-8000-000000000002'),
            new OnboardingWebsiteId('00000000-0000-4000-8000-000000000001'),
            new DateTimeImmutable('2026-07-20T09:00:00+08:00'),
        );
        $onboardingJob->assignWebsiteDesigner(
            new WebsiteDesignerAssignmentId('00000000-0000-4000-8000-000000000005'),
            new PlatformIdentityId('00000000-0000-4000-8000-000000000010'),
            new DateTimeImmutable('2026-07-24T09:30:00+08:00'),
        );
        $onboardingJob->addTask(new OnboardingTask(
            new OnboardingTaskId('00000000-0000-4000-8000-000000000106'),
            $onboardingJob->id,
            $onboardingJob->tenantId,
            'website_setup',
            'Prepare Website',
            OnboardingTaskResponsibility::WebsiteDesigner,
            OnboardingTaskStatus::Completed,
            true,
            true,
            null,
            new DateTimeImmutable('2026-08-07T09:30:00+08:00'),
            'website_configuration_reviewed',
            null,
            null,
            new DateTimeImmutable('2026-07-24T09:30:00+08:00'),
            new DateTimeImmutable('2026-07-24T10:00:00+08:00'),
            new DateTimeImmutable('2026-07-24T10:00:00+08:00'),
        ));
        $onboardingJob->synchronizePersistenceVersion(1);
        $this->app->instance(
            OnboardingJobRepositoryInterface::class,
            new DashboardOnboardingJobRepository($onboardingJob),
        );
        $this->app->instance(
            OnboardingWorkflowTransactionInterface::class,
            new DashboardOnboardingWorkflowTransaction,
        );
        $this->app->instance(WebsiteApprovalAuditInterface::class, new DashboardWebsiteApprovalAudit);
        $this->app->instance(OnboardingAuditInterface::class, new DashboardOnboardingAudit);
        $this->app->instance(
            ClinicOwnerWebsiteApprovalReadInterface::class,
            new DashboardClinicOwnerWebsiteApprovalRead,
        );
        $this->app->instance(
            OnboardingTaskReadInterface::class,
            new DashboardOnboardingTaskRead,
        );
        $this->app->instance(
            LaunchReadinessReadInterface::class,
            new DashboardLaunchReadinessRead,
        );
        $this->app->instance(
            WebsitePublicationApprovalReadInterface::class,
            new DashboardApprovedWebsitePublication,
        );
        $this->app->instance(PlatformDashboardReadInterface::class, new DashboardFixedPlatformRead);
        $this->app->instance(TenantOverviewReadInterface::class, new DashboardFixedTenantOverviewRead);
        $this->app->instance(BillingOverviewReadInterface::class, new DashboardFixedBillingOverviewRead);
        $catalogue = new DashboardFixedCommercialCatalogue;
        $this->app->instance(CommercialCatalogueQueryInterface::class, $catalogue);
        $this->app->instance(PlanCatalogueQueryInterface::class, $catalogue);
        $this->app->instance(PlanOfferingCatalogueQueryInterface::class, $catalogue);
        $this->app->instance(BillingOptionCatalogueQueryInterface::class, $catalogue);
        $this->app->instance(CapabilityDefinitionCatalogueQueryInterface::class, $catalogue);
        $this->app->instance(PricingHistoryReadInterface::class, $catalogue);
        $detail = new DashboardFixedSubscriptionDetailRead;
        $this->app->instance(SubscriptionDetailReadInterface::class, $detail);
        $this->app->instance(SubscriptionTimelineReadInterface::class, $detail);
        $this->app->instance(PaymentHistoryReadInterface::class, $detail);
        $operations = new DashboardRecordedSubscriptionOperations;
        $this->app->instance(ManualRenewSubscriptionInterface::class, $operations);
        $this->app->instance(EnableAutoRenewInterface::class, $operations);
        $this->app->instance(CancelAutoRenewInterface::class, $operations);
        $this->bookingOperations = new DashboardRecordedBookingOperations;
        $this->app->instance(ClinicOwnerBookingOperationsInterface::class, $this->bookingOperations);
    }

    #[DataProvider('authenticatedActors')]
    public function test_every_authenticated_platform_actor_receives_the_same_dashboard_shell(
        ActorType $actorType,
        string $role,
        ?string $tenantId,
        string $component,
    ): void {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization($actorType, $role, $tenantId),
        );

        $response = $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->component($component, false)
                    ->where('pageTitle', 'Dashboard')
                    ->where('identityName', 'Authenticated User')
                    ->where('navigation.0.kind', 'link')
                    ->where('navigation.0.key', 'dashboard')
                    ->where('navigation.0.label', 'Dashboard')
                    ->where('navigation.0.href', route('dashboard'))
                    ->where('navigation.0.current', true)
                    ->has('breadcrumbs', 1),
            );

        if ($actorType === ActorType::ClinicOwner) {
            $response->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->has('navigation', 8)
                    ->where('navigation.3.key', 'services')
                    ->where('welcomeTitle', 'Welcome back, Authenticated User')
                    ->has('summaries', 4)
                    ->where('summaries.0.key', 'clinic')
                    ->where('summaries.0.value', 'Klinik Syifa')
                    ->where('summaries.1.key', 'subscription')
                    ->where('summaries.1.value', 'Active')
                    ->where('summaries.2.key', 'bookings')
                    ->where('summaries.2.value', '1')
                    ->where('summaries.3.key', 'website')
                    ->has('quickActions', 3)
                    ->where('quickActions.0.available', true)
                    ->where('quickActions.0.href', route('dashboard.website'))
                    ->where('quickActions.1.available', true)
                    ->where('quickActions.1.href', route('dashboard.bookings'))
                    ->where('quickActions.2.available', true)
                    ->where('quickActions.2.href', route('dashboard.subscription'))
                    ->where('recentActivity', []),
            );
        } elseif ($role === 'website_designer') {
            $response->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->has('navigation', 3)
                    ->where('navigation.1.key', 'onboarding'),
            );
        } else {
            $response->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->has('navigation', 10)
                    ->where('navigation.1.key', 'registrations')
                    ->where('navigation.2.key', 'tenants'),
            );
        }
    }

    public function test_website_designer_receives_the_dashboard_overview_from_onboarding_query_projections(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::PlatformIdentity,
                'website_designer',
                identityId: '00000000-0000-4000-8000-000000000010',
            ),
        );

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->component('PlatformAdministration/Dashboard/WebsiteDesignerDashboardOverview', false)
                    ->where('contextLabel', 'Website Designer workspace')
                    ->where('welcomeTitle', 'Welcome back, Authenticated User')
                    ->has('summaries', 6)
                    ->where('summaries.0.key', 'assigned-jobs')
                    ->where('summaries.0.value', '3')
                    ->where('summaries.4.key', 'ready-publish')
                    ->where('summaries.5.value', '2')
                    ->has('quickActions', 3)
                    ->where('quickActions.0.available', true)
                    ->where('quickActions.0.href', route('dashboard.onboarding'))
                    ->where('quickActions.1.available', true)
                    ->where('quickActions.1.href', route('dashboard.onboarding', ['status' => 'in_progress']))
                    ->where('quickActions.2.available', true)
                    ->where('quickActions.2.href', route('dashboard.onboarding', ['status' => 'in_review']))
                    ->has('recentAssignments', 1)
                    ->where('recentAssignments.0.description', 'Website setup')
                    ->has('navigation', 3),
            );
    }

    public function test_super_admin_receives_the_platform_dashboard_from_application_projections(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::PlatformIdentity, 'super_admin'),
        );

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->component('PlatformAdministration/Dashboard/SuperAdminDashboardOverview', false)
                    ->where('contextLabel', 'Super Admin workspace')
                    ->has('summaries', 7)
                    ->where('summaries.0.value', '12')
                    ->where('summaries.6.value', 'Operational')
                    ->has('quickActions', 3)
                    ->where('quickActions.0.href', route('dashboard.tenants'))
                    ->where('quickActions.0.available', true)
                    ->where('quickActions.1.href', route('dashboard.billing'))
                    ->where('quickActions.1.available', true)
                    ->where('quickActions.2.href', route('dashboard.commercial'))
                    ->where('quickActions.2.available', true)
                    ->has('navigation', 10)
                    ->where('navigation.1.href', route('dashboard.registrations'))
                    ->where('navigation.2.href', route('dashboard.tenants'))
                    ->where('navigation.3.href', route('dashboard.onboarding-management'))
                    ->where('navigation.4.href', route('dashboard.billing'))
                    ->where('navigation.5.href', route('dashboard.commercial'))
                    ->where('navigation.6.href', route('dashboard.payment-providers'))
                    ->where('navigation.7.href', route('dashboard.notifications'))
                    ->where('navigation.8.href', route('dashboard.audit'))
                    ->where('navigation.9.href', route('dashboard.reports'))
                    ->has('recentActivity', 1),
            );
    }

    public function test_super_admin_can_open_payment_provider_administration_page(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::PlatformIdentity, 'super_admin'),
        );

        $this->get('/dashboard/payment-providers')
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->component('SubscriptionBilling/PaymentProviders/SuperAdminPaymentProviders', false)
                    ->where('contextLabel', 'Super Admin workspace')
                    ->where('providerEndpoints.index', route('payment-providers.index'))
                    ->where('providerEndpoints.health', route('payment-providers.health'))
                    ->has('navigation', 8)
                    ->where('navigation.6.key', 'payment-providers')
                    ->where('navigation.6.current', true)
                    ->where('navigation.7.key', 'audit'),
            );
    }

    public function test_super_admin_can_filter_audit_activity_but_other_roles_cannot(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::PlatformIdentity, 'super_admin'),
        );

        $this->get('/dashboard/audit?action=website&outcome=succeeded&actor_type=system')
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->component('PlatformAdministration/Audit/SuperAdminAuditViewer', false)
                    ->where('contextLabel', 'Super Admin workspace')
                    ->where('filters.action', 'website')
                    ->where('filters.outcome', 'succeeded')
                    ->where('filters.actorType', 'system')
                    ->where('audit.entries.0.action', 'website.published')
                    ->has('navigation', 8)
                    ->where('navigation.7.key', 'audit')
                    ->where('navigation.7.current', true),
            );

        $this->get('/dashboard/audit?tenant_id=not-a-uuid')
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->where('filters.tenantId', null),
            );

        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::PlatformIdentity, 'website_designer'),
        );
        $this->getJson('/dashboard/audit')->assertForbidden();

        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::ClinicOwner,
                'clinic_owner',
                '00000000-0000-4000-8000-000000000002',
                '00000000-0000-4000-8000-000000000010',
            ),
        );
        $this->getJson('/dashboard/audit')->assertForbidden();
    }

    public function test_notification_history_is_role_and_tenant_scoped(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::ClinicOwner,
                'clinic_owner',
                '00000000-0000-4000-8000-000000000002',
            ),
        );

        $this->get('/dashboard/notifications?status=queued')
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->component('Shared/Notifications/NotificationHistory', false)
                    ->where('filters.status', 'queued')
                    ->where('canFilterTenant', false)
                    ->where('notificationHistory.entries.0.category', 'booking_received'),
            );

        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::PlatformIdentity, 'super_admin'),
        );
        $this->get('/dashboard/notifications')
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->where('canFilterTenant', true)
                    ->where('contextLabel', 'Super Admin workspace'),
            );

        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::PlatformIdentity, 'website_designer'),
        );
        $this->getJson('/dashboard/notifications')->assertForbidden();
    }

    public function test_reports_are_projected_for_each_authorized_role_scope(): void
    {
        foreach ([
            [ActorType::ClinicOwner, 'clinic_owner', '00000000-0000-4000-8000-000000000002', 'tenant'],
            [ActorType::PlatformIdentity, 'website_designer', null, 'designer_assignment'],
            [ActorType::PlatformIdentity, 'super_admin', null, 'platform_portfolio'],
        ] as [$actorType, $role, $tenantId, $scope]) {
            $this->app->instance(
                AuthorizationService::class,
                $this->authorization(
                    $actorType,
                    $role,
                    $tenantId,
                    '00000000-0000-4000-8000-000000000010',
                ),
            );

            $this->get('/dashboard/reports')
                ->assertOk()
                ->assertInertia(
                    static fn (AssertableInertia $page): AssertableInertia => $page
                        ->component('Shared/Reports/ReportsOverview', false)
                        ->where('report.scope', $scope)
                        ->has('report.definitions')
                        ->has('report.metrics'),
                );
        }
    }

    public function test_super_admin_can_open_onboarding_management_but_other_roles_cannot(): void
    {
        $this->app->instance(
            SuperAdminOnboardingReadInterface::class,
            new class implements SuperAdminOnboardingReadInterface
            {
                public function overview(?string $status, ?string $search): array
                {
                    return ['jobs' => [], 'designers' => []];
                }
            },
        );
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::PlatformIdentity, 'super_admin'),
        );

        $this->get('/dashboard/onboarding-management')
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->component('PlatformAdministration/Onboarding/SuperAdminOnboarding', false)
                    ->where('contextLabel', 'Super Admin workspace')
                    ->where('onboarding.jobs', [])
                    ->where('onboarding.designers', [])
                    ->where('navigation.3.current', true),
            );

        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::PlatformIdentity, 'website_designer'),
        );
        $this->getJson('/dashboard/onboarding-management')->assertForbidden();

        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::ClinicOwner, 'clinic_owner', 'tenant-1'),
        );
        $this->getJson('/dashboard/onboarding-management')->assertForbidden();
    }

    public function test_super_admin_can_review_registration_portfolio_but_other_roles_cannot(): void
    {
        $this->app->instance(
            ClinicRegistrationReviewReadInterface::class,
            new class implements ClinicRegistrationReviewReadInterface
            {
                public function list(?string $status, int $limit = 100): array
                {
                    return [new RegistrationReviewItemData(
                        '00000000-0000-4000-8000-000000000701',
                        'submitted',
                        'Klinik Baharu',
                        'owner@baharu.test',
                        '+60123456789',
                        '1 Jalan Klinik',
                        '2026-09-02T00:00:00+00:00',
                        3,
                        null,
                        null,
                        null,
                    )];
                }
            },
        );
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::PlatformIdentity, 'super_admin'),
        );

        $this->get('/dashboard/registrations')
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->component('PlatformAdministration/Registrations/SuperAdminRegistrationReview', false)
                    ->where('contextLabel', 'Super Admin workspace')
                    ->has('registrations', 1)
                    ->where('registrations.0.clinicName', 'Klinik Baharu')
                    ->where('navigation.1.current', true),
            );

        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::PlatformIdentity, 'website_designer'),
        );
        $this->getJson('/dashboard/registrations')->assertForbidden();
        $this->postJson('/dashboard/registrations/00000000-0000-4000-8000-000000000701/review', [
            'expected_version' => 3,
        ])->assertForbidden();
        $this->postJson('/dashboard/registrations/00000000-0000-4000-8000-000000000701/decision', [
            'outcome' => 'approved',
            'reason_category' => 'eligible_clinic',
            'expected_version' => 3,
        ])->assertForbidden();

        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::ClinicOwner, 'clinic_owner', 'tenant-1'),
        );
        $this->getJson('/dashboard/registrations')->assertForbidden();
        $this->postJson('/dashboard/registrations/00000000-0000-4000-8000-000000000701/review', [
            'expected_version' => 3,
        ])->assertForbidden();
        $this->postJson('/dashboard/registrations/00000000-0000-4000-8000-000000000701/decision', [
            'outcome' => 'approved',
            'reason_category' => 'eligible_clinic',
            'expected_version' => 3,
        ])->assertForbidden();
    }

    public function test_non_super_admin_actors_cannot_open_payment_provider_administration_page(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::PlatformIdentity,
                'website_designer',
                identityId: '00000000-0000-4000-8000-000000000010',
            ),
        );
        $this->getJson('/dashboard/payment-providers')->assertForbidden();

        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::ClinicOwner, 'clinic_owner', 'tenant-1'),
        );
        $this->getJson('/dashboard/payment-providers')->assertForbidden();
    }

    public function test_super_admin_can_search_and_filter_the_tenant_overview(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::PlatformIdentity, 'super_admin'),
        );

        $this->get('/dashboard/tenants?search=tenant&status=active&per_page=10')
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->component('PlatformAdministration/Tenants/SuperAdminTenantOverview', false)
                    ->where('tenantOverview.search.value', 'tenant')
                    ->where('tenantOverview.statusFilter.value', 'active')
                    ->where('tenantOverview.items.0.id', 'tenant-1')
                    ->where('tenantOverview.items.0.clinicName', 'Klinik Aisyah')
                    ->where('tenantOverview.items.0.ownerName', 'Aisyah Rahman')
                    ->where('tenantOverview.items.0.ownerEmail', 'aisyah@example.test')
                    ->where('tenantOverview.items.0.subscriptionStatusLabel', 'Active')
                    ->where('tenantOverview.items.0.websitePublicationStatus', 'Published')
                    ->where('tenantOverview.items.0.websiteDesigner', 'Designer One'),
            );
    }

    public function test_clinic_owner_and_website_designer_cannot_access_tenant_management(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::ClinicOwner, 'clinic_owner', 'tenant-1'),
        );
        $this->getJson('/dashboard/tenants')->assertForbidden();

        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::PlatformIdentity, 'website_designer'),
        );
        $this->getJson('/dashboard/tenants')->assertForbidden();
    }

    public function test_super_admin_can_search_and_filter_the_billing_overview(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::PlatformIdentity, 'super_admin'),
        );

        $this->get('/dashboard/billing?search=tenant&status=active&per_page=10')
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->component('SubscriptionBilling/Dashboard/SuperAdminBillingOverview', false)
                    ->where('billingOverview.search.value', 'tenant')
                    ->where('billingOverview.statusFilter.value', 'active')
                    ->where('billingOverview.summary.0.value', 8)
                    ->where('billingOverview.summary.3.value', 'MYR 1,234.56')
                    ->where('billingOverview.subscriptions.0.tenantId', 'tenant-1')
                    ->where('billingOverview.recentPayments.0.statusLabel', 'Succeeded')
                    ->where('billingOverview.health.status', 'healthy'),
            );
    }

    public function test_clinic_owner_and_website_designer_cannot_access_billing_overview(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::ClinicOwner, 'clinic_owner', 'tenant-1'),
        );
        $this->getJson('/dashboard/billing')->assertForbidden();
        $this->getJson('/dashboard/billing/subscriptions/11111111-1111-4111-8111-111111111111')->assertForbidden();
        $this->postJson('/dashboard/billing/subscriptions/11111111-1111-4111-8111-111111111111/renew')->assertForbidden();

        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::PlatformIdentity, 'website_designer'),
        );
        $this->getJson('/dashboard/billing')->assertForbidden();
        $this->getJson('/dashboard/billing/subscriptions/11111111-1111-4111-8111-111111111111')->assertForbidden();
        $this->postJson('/dashboard/billing/subscriptions/11111111-1111-4111-8111-111111111111/auto-renew/enable')->assertForbidden();
    }

    public function test_super_admin_can_view_read_only_subscription_detail(): void
    {
        $this->app->instance(AuthorizationService::class, $this->authorization(ActorType::PlatformIdentity, 'super_admin'));

        $this->get('/dashboard/billing/subscriptions/11111111-1111-4111-8111-111111111111')
            ->assertOk()
            ->assertInertia(static fn (AssertableInertia $page): AssertableInertia => $page
                ->component('SubscriptionBilling/Dashboard/SuperAdminSubscriptionDetail', false)
                ->where('subscription.status', 'Renewal Due')
                ->where('subscription.autoRenewStatus', 'Not Configured')
                ->has('timeline', 1)
                ->where('payments.0.purpose', 'Initial Activation')
                ->where(
                    'actions.checkout.action',
                    route('renewal-checkouts.start', '22222222-2222-4222-8222-222222222222'),
                )
                ->where('actions.checkout.label', 'Start Renewal Checkout'));

        $this->withSession(['operation' => 'enabled'])
            ->get('/dashboard/billing/subscriptions/11111111-1111-4111-8111-111111111111')
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->where('feedback.success', 'Auto-renew enabled successfully.')
                    ->where('feedback.error', null),
            );
    }

    public function test_ineligible_subscription_does_not_expose_renewal_checkout(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::PlatformIdentity, 'super_admin'),
        );
        $this->app->instance(
            SubscriptionDetailReadInterface::class,
            new class implements SubscriptionDetailReadInterface
            {
                public function detail(string $subscriptionId): ?SubscriptionDetailData
                {
                    return new SubscriptionDetailData(
                        $subscriptionId,
                        'tenant-1',
                        'essential',
                        'annual',
                        120000,
                        'MYR',
                        '2026-01-01',
                        '2026-12-31',
                        'active',
                        'not_due',
                        'disabled',
                        2,
                    );
                }
            },
        );

        $this->get('/dashboard/billing/subscriptions/11111111-1111-4111-8111-111111111111')
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->where('actions.checkout', null),
            );
    }

    public function test_super_admin_can_delegate_subscription_operations(): void
    {
        $this->app->instance(AuthorizationService::class, $this->authorization(ActorType::PlatformIdentity, 'super_admin'));
        $id = '11111111-1111-4111-8111-111111111111';

        $this->post("/dashboard/billing/subscriptions/{$id}/renew", ['expected_version' => 2, 'idempotency_key' => 'renewal-key'])
            ->assertRedirect()
            ->assertSessionHas('operation', 'accepted');
        $this->post("/dashboard/billing/subscriptions/{$id}/auto-renew/enable", ['expected_version' => 2])
            ->assertRedirect()
            ->assertSessionHas('operation', 'enabled');
        $this->post("/dashboard/billing/subscriptions/{$id}/auto-renew/disable", ['expected_version' => 2])
            ->assertRedirect()
            ->assertSessionHas('operation', 'cancelled');

        $this->from("/dashboard/billing/subscriptions/{$id}")
            ->post("/dashboard/billing/subscriptions/{$id}/renew", [
                'expected_version' => 0,
                'idempotency_key' => '',
            ])
            ->assertRedirect("/dashboard/billing/subscriptions/{$id}")
            ->assertSessionHasErrors(['expected_version', 'idempotency_key']);
    }

    public function test_super_admin_can_view_commercial_plans_offerings_features_and_pricing_history(): void
    {
        $this->app->instance(AuthorizationService::class, $this->authorization(ActorType::PlatformIdentity, 'super_admin'));
        $planId = '10000000-0000-4000-8000-000000000001';
        $offeringId = '10000000-0000-4000-8000-000000000003';

        $this->get('/dashboard/commercial')
            ->assertOk()
            ->assertInertia(static function (AssertableInertia $page): AssertableInertia {
                return $page
                    ->component('SubscriptionBilling/Commercial/SuperAdminCommercialManagement', false)
                    ->where('plans.0.name', 'Syifa Essential')
                    ->where('actions.createPlan', route('dashboard.commercial.plans.create'))
                    ->where(
                        'actions.createBillingOption',
                        route('dashboard.commercial.billing-options.create'),
                    )
                    ->where('billingOptions.0.code', 'annual')
                    ->where('capabilities.0.version', 1)
                    ->where(
                        'capabilities.0.editUrl',
                        route(
                            'dashboard.commercial.capabilities.edit',
                            '10000000-0000-4000-8000-000000000004',
                        ),
                    )
                    ->where(
                        'actions.createCapability',
                        route('dashboard.commercial.capabilities.create'),
                    )
                    ->where(
                        'billingOptions.0.editUrl',
                        route(
                            'dashboard.commercial.billing-options.edit',
                            '10000000-0000-4000-8000-000000000002',
                        ),
                    )
                    ->missing('validationErrors')
                    ->missing('oldInput')
                    ->where('selectedPlan', null);
            });

        $this->get('/dashboard/commercial/plans/create')
            ->assertOk()
            ->assertInertia(static fn (AssertableInertia $page): AssertableInertia => $page
                ->component('SubscriptionBilling/Commercial/SuperAdminCommercialMutationForm', false)
                ->where('formKind', 'plan-create')
                ->where('action', route('dashboard.commercial.plans.store'))
                ->where('validationErrors', []));

        $this->get('/dashboard/commercial/billing-options/create')
            ->assertOk()
            ->assertInertia(static fn (AssertableInertia $page): AssertableInertia => $page
                ->component('SubscriptionBilling/Commercial/SuperAdminCommercialMutationForm', false)
                ->where('formKind', 'billing-option-create')
                ->where('action', route('dashboard.commercial.billing-options.store'))
                ->where('validationErrors', []));

        $this->get('/dashboard/commercial/capabilities/create')
            ->assertOk()
            ->assertInertia(static fn (AssertableInertia $page): AssertableInertia => $page
                ->component('SubscriptionBilling/Commercial/SuperAdminCommercialMutationForm', false)
                ->where('formKind', 'capability-create')
                ->where('action', route('dashboard.commercial.capabilities.store'))
                ->where('validationErrors', []));

        $this->get('/dashboard/commercial/capabilities/10000000-0000-4000-8000-000000000004/edit')
            ->assertOk()
            ->assertInertia(static fn (AssertableInertia $page): AssertableInertia => $page
                ->component('SubscriptionBilling/Commercial/SuperAdminCommercialMutationForm', false)
                ->where('formKind', 'capability-edit')
                ->where('capability.name', 'Booking management')
                ->where('capability.version', 1)
                ->where(
                    'action',
                    route(
                        'dashboard.commercial.capabilities.update',
                        '10000000-0000-4000-8000-000000000004',
                    ),
                ));

        $this->get('/dashboard/commercial/billing-options/10000000-0000-4000-8000-000000000002/edit')
            ->assertOk()
            ->assertInertia(static fn (AssertableInertia $page): AssertableInertia => $page
                ->component('SubscriptionBilling/Commercial/SuperAdminCommercialMutationForm', false)
                ->where('formKind', 'billing-option-edit')
                ->where('billingOption.name', 'Annual')
                ->where('billingOption.version', 1)
                ->where(
                    'action',
                    route(
                        'dashboard.commercial.billing-options.update',
                        '10000000-0000-4000-8000-000000000002',
                    ),
                ));

        $this->get("/dashboard/commercial/plans/{$planId}/edit")
            ->assertOk()
            ->assertInertia(static fn (AssertableInertia $page): AssertableInertia => $page
                ->component('SubscriptionBilling/Commercial/SuperAdminCommercialMutationForm', false)
                ->where('formKind', 'plan-edit')
                ->where('plan.name', 'Syifa Essential'));

        $this->withSession(['operation' => 'offering_updated'])
            ->get("/dashboard/commercial/plans/{$planId}/offerings/{$offeringId}")
            ->assertOk()
            ->assertInertia(static fn (AssertableInertia $page): AssertableInertia => $page
                ->where('selectedPlan.name', 'Syifa Essential')
                ->where('selectedPlan.version', 1)
                ->where('selectedOffering.amount', 'MYR 1,200.00')
                ->where('selectedOffering.version', 2)
                ->where('capabilities.0.key', 'booking.manage')
                ->where('pricingHistory.0.version', 2)
                ->where(
                    'actions.publishPlan',
                    route('dashboard.commercial.plans.publish', $planId),
                )
                ->where(
                    'actions.retirePlan',
                    route('dashboard.commercial.plans.retire', $planId),
                )
                ->where(
                    'actions.unavailablePlan',
                    route('dashboard.commercial.plans.unavailable', $planId),
                )
                ->where(
                    'actions.grandfatherPlan',
                    route('dashboard.commercial.plans.grandfather', $planId),
                )
                ->where(
                    'actions.editOffering',
                    route('dashboard.commercial.plans.offerings.edit', [
                        'planId' => $planId,
                        'offeringId' => $offeringId,
                    ]),
                )
                ->where(
                    'actions.unavailable',
                    route('dashboard.commercial.offerings.unavailable', $offeringId),
                )
                ->where(
                    'actions.grandfather',
                    route('dashboard.commercial.offerings.grandfather', $offeringId),
                )
                ->where('feedback.success', 'Plan offering updated successfully.'));

        $this->get("/dashboard/commercial/plans/{$planId}/offerings/create")
            ->assertOk()
            ->assertInertia(static fn (AssertableInertia $page): AssertableInertia => $page
                ->component('SubscriptionBilling/Commercial/SuperAdminCommercialMutationForm', false)
                ->where('formKind', 'offering-create')
                ->where('plan.id', $planId));

        $this->get("/dashboard/commercial/plans/{$planId}/offerings/{$offeringId}/edit")
            ->assertOk()
            ->assertInertia(static fn (AssertableInertia $page): AssertableInertia => $page
                ->component('SubscriptionBilling/Commercial/SuperAdminCommercialMutationForm', false)
                ->where('formKind', 'offering-edit')
                ->where('offering.id', $offeringId)
                ->where('offering.version', 2));
    }

    public function test_non_super_admin_roles_cannot_access_commercial_management(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::ClinicOwner, 'clinic_owner', 'tenant-1'),
        );
        $this->getJson('/dashboard/commercial')->assertForbidden();
        $this->getJson('/dashboard/commercial/plans/create')->assertForbidden();
        $this->getJson('/dashboard/commercial/billing-options/create')->assertForbidden();
        $this->getJson('/dashboard/commercial/capabilities/create')->assertForbidden();
        $this->getJson('/dashboard/commercial/billing-options/10000000-0000-4000-8000-000000000002/edit')->assertForbidden();
        $this->postJson('/dashboard/commercial/plans')->assertForbidden();
        $this->postJson('/dashboard/commercial/billing-options')->assertForbidden();
        $this->postJson('/dashboard/commercial/capabilities')->assertForbidden();
        $this->patchJson('/dashboard/commercial/billing-options/10000000-0000-4000-8000-000000000002')->assertForbidden();
        $this->postJson('/dashboard/commercial/offerings')->assertForbidden();

        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::PlatformIdentity, 'website_designer'),
        );
        $this->getJson('/dashboard/commercial')->assertForbidden();
        $this->postJson('/dashboard/commercial/plans/10000000-0000-4000-8000-000000000001/publish')
            ->assertForbidden();
        $this->postJson('/dashboard/commercial/plans/10000000-0000-4000-8000-000000000001/retire')
            ->assertForbidden();
        $this->postJson('/dashboard/commercial/plans/10000000-0000-4000-8000-000000000001/unavailable')
            ->assertForbidden();
        $this->postJson('/dashboard/commercial/plans/10000000-0000-4000-8000-000000000001/grandfather')
            ->assertForbidden();
        $this->postJson('/dashboard/commercial/offerings/10000000-0000-4000-8000-000000000003/retire')->assertForbidden();
        $this->postJson('/dashboard/commercial/offerings/10000000-0000-4000-8000-000000000003/unavailable')
            ->assertForbidden();
        $this->postJson('/dashboard/commercial/offerings/10000000-0000-4000-8000-000000000003/grandfather')
            ->assertForbidden();
    }

    public function test_invalid_commercial_values_return_clear_validation_errors(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::PlatformIdentity, 'super_admin'),
        );

        $this->from('/dashboard/commercial/plans/10000000-0000-4000-8000-000000000001/offerings/create')
            ->post('/dashboard/commercial/offerings', [
                'plan_id' => '10000000-0000-4000-8000-000000000001',
                'billing_option_id' => '10000000-0000-4000-8000-000000000002',
                'amount_minor' => -1,
                'effective_start' => '2026-01-01',
                'effective_end' => null,
                'capability_configuration_reference' => 'essential-v2',
                'display_order' => 1,
            ])
            ->assertStatus(303)
            ->assertRedirect('/dashboard/commercial/plans/10000000-0000-4000-8000-000000000001/offerings/create')
            ->assertSessionHasErrors('amount_minor', errorBag: 'commercial');

        $response = $this->from('/dashboard/commercial/plans/create')
            ->post('/dashboard/commercial/plans', [
                'code' => '',
                'name' => '',
                'description' => '',
                'display_order' => -1,
            ]);
        $response
            ->assertStatus(303)
            ->assertRedirect('/dashboard/commercial/plans/create')
            ->assertSessionHasErrors(
                ['code', 'name', 'description', 'display_order'],
                errorBag: 'commercial',
            );

        $this->get('/dashboard/commercial/plans/create')
            ->assertOk()
            ->assertInertia(static fn (AssertableInertia $page): AssertableInertia => $page
                ->component('SubscriptionBilling/Commercial/SuperAdminCommercialMutationForm', false)
                ->where('validationErrors', [
                    'The code field is required.',
                    'The name field is required.',
                    'The description field is required.',
                    'The display order field must be at least 0.',
                ])
                ->where('oldInput.display_order', -1));

        $this->get('/dashboard/commercial/plans/create')
            ->assertOk()
            ->assertInertia(static fn (AssertableInertia $page): AssertableInertia => $page
                ->where('validationErrors', [])
                ->where('oldInput.code', null));

        $this->get('/dashboard/commercial')
            ->assertOk()
            ->assertInertia(static fn (AssertableInertia $page): AssertableInertia => $page
                ->missing('validationErrors')
                ->missing('oldInput'));

    }

    public function test_ordinary_commercial_get_has_no_validation_errors(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::PlatformIdentity, 'super_admin'),
        );

        $this->get('/dashboard/commercial')
            ->assertOk()
            ->assertInertia(static fn (AssertableInertia $page): AssertableInertia => $page
                ->missing('validationErrors')
                ->missing('oldInput'));
    }

    public function test_website_designer_can_search_and_filter_the_assigned_onboarding_queue(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::PlatformIdentity, 'website_designer'),
        );

        $this->get('/dashboard/onboarding?search=job&status=in_progress&per_page=10')
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->component('PlatformAdministration/Onboarding/WebsiteDesignerOnboardingQueue', false)
                    ->where('pageTitle', 'Onboarding queue')
                    ->where('navigation.1.key', 'onboarding')
                    ->where('navigation.1.current', true)
                    ->where('onboardingQueue.search.value', 'job')
                    ->where('onboardingQueue.statusFilter.value', 'in_progress')
                    ->where('onboardingQueue.items.0.id', 'job-1')
                    ->where('onboardingQueue.items.0.websiteSetup', 'Current')
                    ->where('onboardingQueue.items.0.publishReadiness', 'Not current')
                    ->where('onboardingQueue.pagination.perPage', 10),
            );
    }

    public function test_clinic_owner_and_super_admin_cannot_access_the_designer_queue(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::ClinicOwner, 'clinic_owner', 'tenant-1'),
        );
        $this->getJson('/dashboard/onboarding')->assertForbidden();

        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::PlatformIdentity, 'super_admin'),
        );
        $this->getJson('/dashboard/onboarding')->assertForbidden();
    }

    public function test_launch_readiness_endpoint_enforces_role_and_resource_scope(): void
    {
        $jobId = DashboardLaunchReadinessRead::JOB_ID;

        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::PlatformIdentity,
                'website_designer',
                identityId: '00000000-0000-4000-8000-000000000010',
            ),
        );
        $this->getJson('/api/v1/onboarding-jobs/'.$jobId.'/launch-readiness')
            ->assertOk()
            ->assertJsonPath('data.onboardingJobId', $jobId)
            ->assertJsonPath('data.tenantId', DashboardLaunchReadinessRead::TENANT_ID)
            ->assertJsonPath('data.ready', false);

        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::PlatformIdentity,
                'website_designer',
                identityId: '00000000-0000-4000-8000-000000000999',
            ),
        );
        $this->getJson('/api/v1/onboarding-jobs/'.$jobId.'/launch-readiness')
            ->assertNotFound();

        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::ClinicOwner,
                'clinic_owner',
                DashboardLaunchReadinessRead::TENANT_ID,
            ),
        );
        $this->getJson('/api/v1/onboarding-jobs/'.$jobId.'/launch-readiness')
            ->assertOk()
            ->assertJsonPath('data.onboardingJobId', $jobId);

        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::ClinicOwner, 'clinic_owner', 'tenant-foreign'),
        );
        $this->getJson('/api/v1/onboarding-jobs/'.$jobId.'/launch-readiness')
            ->assertNotFound();

        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::PlatformIdentity, 'super_admin'),
        );
        $this->getJson('/api/v1/onboarding-jobs/'.$jobId.'/launch-readiness')
            ->assertOk()
            ->assertJsonPath('data.onboardingJobId', $jobId);
    }

    public function test_website_designer_receives_assignment_scoped_operational_job_detail(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::PlatformIdentity,
                'website_designer',
                identityId: '00000000-0000-4000-8000-000000000010',
            ),
        );
        $jobId = '00000000-0000-4000-8000-000000000101';

        $this->get('/dashboard/onboarding/'.$jobId)
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->component('PlatformAdministration/Onboarding/WebsiteDesignerJobDetail', false)
                    ->where('job.id', $jobId)
                    ->where('job.status', 'in_progress')
                    ->where('job.progress.value', 50)
                    ->has('job.stages', 4)
                    ->where('job.stages.1.state', 'current')
                    ->has('job.timeline', 3)
                    ->where('job.actions.0.available', true)
                    ->where('job.actions.1.available', true)
                    ->where('job.actions.2.available', true)
                    ->where(
                        'job.actions.2.href',
                        route('dashboard.onboarding.custom-domain', ['jobId' => $jobId]),
                    )
                    ->where('websiteSetup.configuration.branding.clinic_name', 'Klinik Aisyah')
                    ->where('websiteSetup.configuration.template_id', 'SYIFA_ESSENTIAL')
                    ->has('websiteSetup.templateOptions', 5)
                    ->where('bookingSetup.configuration.service_selection_enabled', false)
                    ->where('bookingSetup.configuration.active_services.0.name', 'Consultation')
                    ->where('clinicContact.configuration.operational_phone', '+60312345678')
                    ->where('clinicContact.configuration.version', 1)
                    ->where('websiteSetup.updateUrl', route('dashboard.onboarding.show', $jobId))
                    ->where(
                        'websiteSetup.previewUrl',
                        route('dashboard.onboarding.preview', $jobId),
                    )
                    ->where('websiteSetup.configuration.lifecycle', 'draft')
                    ->where('websiteSetup.canSubmitForReview', true)
                    ->where('websiteDraft.draft.version', 1)
                    ->where('websiteDraft.draft.sections.0.type', 'HERO')
                    ->where('websiteDraft.draft.sections.1.type', 'ABOUT')
                    ->where(
                        'websiteDraft.updateUrl',
                        route('website-designer.website-draft.update', $jobId),
                    ),
            );
    }

    public function test_assigned_designer_submits_an_eligible_website_for_review_and_stale_retry_conflicts(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::PlatformIdentity,
                'website_designer',
                identityId: '00000000-0000-4000-8000-000000000010',
            ),
        );
        $jobId = '00000000-0000-4000-8000-000000000101';

        $this->postJson('/dashboard/onboarding/'.$jobId.'/website-address', [
            'subdomain' => 'klinik-aisyah',
        ])->assertCreated();
        $this->patchJson('/dashboard/onboarding/'.$jobId, [
            'workspace' => 'ready_for_review',
            'version' => 1,
            'draft_version' => 1,
            'job_version' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('data.lifecycle', 'ready_for_review')
            ->assertJsonPath('data.version', 2);

        $this->patchJson('/dashboard/onboarding/'.$jobId, [
            'workspace' => 'ready_for_review',
            'version' => 1,
            'draft_version' => 1,
            'job_version' => 1,
        ])->assertConflict();

        $this->get('/dashboard/onboarding/'.$jobId)
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->where('websiteSetup.configuration.lifecycle', 'ready_for_review')
                    ->where('websiteSetup.canSubmitForReview', true)
                    ->where('job.timeline.3.key', 'website_ready_for_review'),
            );
    }

    public function test_clinic_owner_cannot_submit_a_website_for_review(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::ClinicOwner, 'clinic_owner', 'tenant-1'),
        );

        $this->patchJson(
            '/dashboard/onboarding/00000000-0000-4000-8000-000000000101',
            ['workspace' => 'ready_for_review', 'version' => 1],
        )->assertForbidden();
    }

    public function test_only_super_admin_can_cancel_and_reopen_an_onboarding_job(): void
    {
        $jobId = '00000000-0000-4000-8000-000000000101';
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::PlatformIdentity,
                'website_designer',
                identityId: '00000000-0000-4000-8000-000000000010',
            ),
        );
        $this->postJson('/dashboard/onboarding-management/'.$jobId.'/lifecycle', [
            'operation' => 'cancel',
            'reason' => 'Testing forbidden access.',
            'expected_version' => 1,
        ])->assertForbidden();

        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::PlatformIdentity,
                'super_admin',
                identityId: '00000000-0000-4000-8000-000000000090',
            ),
        );
        $this->postJson('/dashboard/onboarding-management/'.$jobId.'/lifecycle', [
            'operation' => 'cancel',
            'reason' => 'Customer requested cancellation.',
            'expected_version' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('status', 'cancelled')
            ->assertJsonPath('version', 2);
        $this->postJson('/dashboard/onboarding-management/'.$jobId.'/lifecycle', [
            'operation' => 'reopen',
            'reason' => 'Customer resumed onboarding.',
            'expected_version' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('status', 'reopened')
            ->assertJsonPath('version', 3);
    }

    public function test_only_the_tenant_clinic_owner_can_approve_the_designer_submission(): void
    {
        $jobId = '00000000-0000-4000-8000-000000000101';
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::PlatformIdentity,
                'website_designer',
                identityId: '00000000-0000-4000-8000-000000000010',
            ),
        );
        $this->patchJson('/dashboard/onboarding/'.$jobId, [
            'workspace' => 'ready_for_review',
            'version' => 1,
            'draft_version' => 1,
            'job_version' => 1,
        ])->assertOk();

        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::ClinicOwner,
                'clinic_owner',
                '00000000-0000-4000-8000-000000000099',
                identityId: '00000000-0000-4000-8000-000000000040',
            ),
        );
        $this->postJson('/dashboard/website/approval', [
            'job_id' => $jobId,
            'expected_version' => 2,
            'decision' => 'approve',
        ])->assertConflict();

        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::ClinicOwner,
                'clinic_owner',
                '00000000-0000-4000-8000-000000000002',
                identityId: '00000000-0000-4000-8000-000000000040',
            ),
        );
        $this->postJson('/dashboard/website/approval', [
            'job_id' => $jobId,
            'expected_version' => 2,
            'decision' => 'approve',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ready_for_launch')
            ->assertJsonPath('version', 3);
    }

    public function test_assigned_designer_publishes_reviewed_website_once_and_refreshes_authoritative_detail(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::PlatformIdentity,
                'website_designer',
                identityId: '00000000-0000-4000-8000-000000000010',
            ),
        );
        $jobId = '00000000-0000-4000-8000-000000000101';

        $this->postJson('/dashboard/onboarding/'.$jobId.'/website-address', [
            'subdomain' => 'klinik-aisyah',
        ])->assertCreated();
        $this->patchJson('/dashboard/onboarding/'.$jobId, [
            'workspace' => 'ready_for_review',
            'version' => 1,
            'draft_version' => 1,
            'job_version' => 1,
        ])->assertOk();

        $this->postJson('/dashboard/onboarding/'.$jobId.'/publish', [
            'website_version' => 2,
            'draft_version' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('data.lifecycle', 'published')
            ->assertJsonPath('data.published_version', 1);

        self::assertTrue($this->websiteRepository->hasPublishedSnapshot());
        self::assertSame('Trusted healthcare', $this->websiteRepository
            ->publishedHeadline());

        $this->postJson('/dashboard/onboarding/'.$jobId.'/publish', [
            'website_version' => 2,
            'draft_version' => 1,
        ])->assertConflict();

        $this->get('/dashboard/onboarding/'.$jobId)
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->where('websiteSetup.configuration.lifecycle', 'published')
                    ->where('websiteSetup.configuration.published_version', 1)
                    ->where('websiteSetup.canPublish', false)
                    ->where('websiteSetup.address.host', 'klinik-aisyah.syifa.my')
                    ->where('websiteSetup.address.active', true)
                    ->where('websiteSetup.address.url', 'https://klinik-aisyah.syifa.my')
                    ->where('job.timeline.3.key', 'website_published'),
            );
    }

    public function test_assigned_designer_checks_and_reserves_normalized_website_subdomain(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::PlatformIdentity,
                'website_designer',
                identityId: '00000000-0000-4000-8000-000000000010',
            ),
        );
        $url = '/dashboard/onboarding/00000000-0000-4000-8000-000000000101/website-address';

        $this->getJson($url.'?subdomain=Klinik-Aisyah')
            ->assertOk()
            ->assertJsonPath('available', true);
        $this->postJson($url, ['subdomain' => 'Klinik-Aisyah'])
            ->assertCreated()
            ->assertJsonPath('data.host', 'klinik-aisyah.syifa.my')
            ->assertJsonPath('data.status', 'preparing')
            ->assertJsonPath('data.active', false);
        $this->postJson($url, ['subdomain' => 'not valid'])
            ->assertUnprocessable();

        $this->get('/dashboard/onboarding/00000000-0000-4000-8000-000000000101')
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->where('websiteSetup.address.host', 'klinik-aisyah.syifa.my')
                    ->where('websiteSetup.address.status', 'preparing')
                    ->where('websiteSetup.canReserveAddress', true),
            );
    }

    public function test_website_address_reservation_rejects_other_roles_and_unassigned_designers(): void
    {
        $url = '/dashboard/onboarding/00000000-0000-4000-8000-000000000101/website-address';

        $this->postJson($url, ['subdomain' => 'clinic'])->assertForbidden();
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::ClinicOwner, 'clinic_owner', 'tenant-1'),
        );
        $this->postJson($url, ['subdomain' => 'clinic'])->assertForbidden();
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::PlatformIdentity, 'super_admin'),
        );
        $this->postJson($url, ['subdomain' => 'clinic'])->assertForbidden();
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::PlatformIdentity,
                'website_designer',
                identityId: '00000000-0000-4000-8000-000000000010',
            ),
        );
        $this->postJson(
            '/dashboard/onboarding/00000000-0000-4000-8000-000000000999/website-address',
            ['subdomain' => 'clinic'],
        )->assertNotFound();
    }

    public function test_publish_route_rejects_other_roles_and_unassigned_designers(): void
    {
        $url = '/dashboard/onboarding/00000000-0000-4000-8000-000000000101/publish';
        $payload = ['website_version' => 1, 'draft_version' => 1];

        $this->postJson($url, $payload)->assertForbidden();

        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::ClinicOwner, 'clinic_owner', 'tenant-1'),
        );
        $this->postJson($url, $payload)->assertForbidden();

        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::PlatformIdentity, 'super_admin'),
        );
        $this->postJson($url, $payload)->assertForbidden();

        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::PlatformIdentity,
                'website_designer',
                identityId: '00000000-0000-4000-8000-000000000010',
            ),
        );
        $this->postJson(
            '/dashboard/onboarding/00000000-0000-4000-8000-000000000999/publish',
            $payload,
        )->assertNotFound();
    }

    public function test_publish_rejects_a_website_that_has_not_passed_readiness(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::PlatformIdentity,
                'website_designer',
                identityId: '00000000-0000-4000-8000-000000000010',
            ),
        );

        $this->postJson(
            '/dashboard/onboarding/00000000-0000-4000-8000-000000000101/publish',
            ['website_version' => 1, 'draft_version' => 1],
        )->assertUnprocessable()->assertJsonPath(
            'detail',
            'Website is not ready to publish.',
        );
        self::assertFalse($this->websiteRepository->hasPublishedSnapshot());
    }

    public function test_publish_fails_closed_without_an_active_subscription_entitlement(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::PlatformIdentity,
                'website_designer',
                identityId: '00000000-0000-4000-8000-000000000010',
            ),
        );
        $jobId = '00000000-0000-4000-8000-000000000101';
        $this->postJson('/dashboard/onboarding/'.$jobId.'/website-address', [
            'subdomain' => 'klinik-aisyah',
        ])->assertCreated();
        $this->patchJson('/dashboard/onboarding/'.$jobId, [
            'workspace' => 'ready_for_review',
            'version' => 1,
            'draft_version' => 1,
            'job_version' => 1,
        ])->assertOk();
        $this->app->instance(
            SubscriptionSummaryReadInterface::class,
            new DashboardInactiveSubscriptionSummary,
        );

        $this->postJson('/dashboard/onboarding/'.$jobId.'/publish', [
            'website_version' => 2,
            'draft_version' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'detail',
                'Website publication requires an active Subscription entitlement.',
            );
        self::assertFalse($this->websiteRepository->hasPublishedSnapshot());
    }

    public function test_assigned_designer_previews_current_draft_without_publication_mutation(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::PlatformIdentity,
                'website_designer',
                identityId: '00000000-0000-4000-8000-000000000010',
            ),
        );
        $jobId = '00000000-0000-4000-8000-000000000101';

        $this->get('/dashboard/onboarding/'.$jobId.'/preview')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Draft Preview')
            ->assertSee('Trusted healthcare')
            ->assertSee('data-template="syifa-essential"', false)
            ->assertSee(route('dashboard.onboarding.booking-preview', $jobId), false)
            ->assertSee('<meta name="robots" content="noindex,nofollow,noarchive">', false)
            ->assertDontSee('About Klinik');

        self::assertSame('draft', $this->websiteRepository->lifecycle());
        self::assertFalse($this->websiteRepository->hasPublishedSnapshot());

        $this->websiteRepository->enableIncompleteAbout();
        $this->websiteDraftRepository->replaceHero('Draft changes appear immediately');
        $this->get('/dashboard/onboarding/'.$jobId.'/preview')
            ->assertOk()
            ->assertSee('Draft changes appear immediately')
            ->assertDontSee('About Klinik');
        self::assertSame('draft', $this->websiteRepository->lifecycle());
        self::assertFalse($this->websiteRepository->hasPublishedSnapshot());
    }

    public function test_clinic_owner_previews_only_their_current_draft_before_approval(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::ClinicOwner,
                'clinic_owner',
                DashboardLaunchReadinessRead::TENANT_ID,
                identityId: '00000000-0000-4000-8000-000000000040',
            ),
        );

        $this->get(route('dashboard.website.preview'))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Draft Preview')
            ->assertSee('Trusted healthcare');

        self::assertSame('draft', $this->websiteRepository->lifecycle());
        self::assertFalse($this->websiteRepository->hasPublishedSnapshot());

        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::ClinicOwner,
                'clinic_owner',
                '00000000-0000-4000-8000-000000000099',
                identityId: '00000000-0000-4000-8000-000000000041',
            ),
        );
        $this->get(route('dashboard.website.preview'))->assertNotFound();
    }

    public function test_assigned_designer_opens_the_protected_booking_form_preview(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::PlatformIdentity,
                'website_designer',
                identityId: '00000000-0000-4000-8000-000000000010',
            ),
        );
        $jobId = '00000000-0000-4000-8000-000000000101';

        $this->get(route('dashboard.onboarding.booking-preview', $jobId))
            ->assertOk()
            ->assertSee('Booking Preview')
            ->assertSee('Book an appointment')
            ->assertSee('Appointment date')
            ->assertSee('Check available times')
            ->assertSee('Submit booking')
            ->assertSee('<meta name="robots" content="noindex">', false);

        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::ClinicOwner, 'clinic_owner', 'tenant-1'),
        );
        $this->get(route('dashboard.onboarding.booking-preview', $jobId))->assertForbidden();
    }

    public function test_draft_preview_rejects_other_roles_public_visitors_and_unassigned_jobs(): void
    {
        $preview = '/dashboard/onboarding/00000000-0000-4000-8000-000000000101/preview';

        $this->getJson($preview)->assertForbidden();

        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::ClinicOwner, 'clinic_owner', 'tenant-1'),
        );
        $this->getJson($preview)->assertForbidden();

        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::PlatformIdentity,
                'website_designer',
                identityId: '00000000-0000-4000-8000-000000000010',
            ),
        );
        $this->getJson(
            '/dashboard/onboarding/00000000-0000-4000-8000-000000000999/preview',
        )->assertNotFound();
    }

    public function test_website_designer_updates_the_assigned_website_setup(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::PlatformIdentity,
                'website_designer',
                identityId: '00000000-0000-4000-8000-000000000010',
            ),
        );
        $jobId = '00000000-0000-4000-8000-000000000101';

        $this->patch('/dashboard/onboarding/'.$jobId, [
            'workspace' => 'website_setup',
            'version' => 1,
            'template_id' => 'SYIFA_CARE',
            'branding' => [
                'clinic_name' => 'Klinik Designer',
                'tagline' => 'Configured with care',
                'primary_color' => '#aabbcc',
                'secondary_color' => '#ddeeff',
                'logo_reference' => null,
                'logo_display_size' => 'large',
                'contact_email' => 'designer@example.test',
                'contact_phone' => '+60123456789',
                'address' => 'Kuala Lumpur',
                'social_links' => [
                    'facebook' => 'https://facebook.com/klinik',
                    'instagram' => null,
                    'youtube' => null,
                    'tiktok' => null,
                    'linkedin' => null,
                ],
            ],
            'seo' => [
                'meta_title' => 'Klinik Designer SEO',
                'meta_description' => 'Trusted healthcare configured by the assigned designer.',
                'meta_keywords' => 'clinic, healthcare',
                'canonical_url' => 'https://clinic.example.test',
                'robots_directive' => 'index,nofollow',
                'open_graph_title' => 'Klinik Designer',
                'open_graph_description' => 'Trusted clinic information for social sharing.',
                'indexing_enabled' => false,
            ],
            'sections' => [
                'hero' => true,
                'about' => false,
                'services' => true,
                'doctors' => true,
                'testimonials' => true,
                'gallery' => true,
                'faq' => true,
                'contact' => true,
                'booking_cta' => true,
            ],
        ])->assertRedirect();

        $this->get('/dashboard/onboarding/'.$jobId)
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->where('websiteSetup.configuration.branding.clinic_name', 'Klinik Designer')
                    ->where('websiteSetup.configuration.branding.primary_color', '#AABBCC')
                    ->where('websiteSetup.configuration.branding.secondary_color', '#DDEEFF')
                    ->where('websiteSetup.configuration.template_id', 'SYIFA_CARE')
                    ->where('websiteSetup.configuration.seo.meta_title', 'Klinik Designer SEO')
                    ->where('websiteSetup.configuration.seo.canonical_url', 'https://clinic.example.test')
                    ->where('websiteSetup.configuration.seo.robots_directive', 'index,nofollow')
                    ->where('websiteSetup.configuration.seo.indexing_enabled', false)
                    ->where('websiteSetup.configuration.sections.1.key', 'about')
                    ->where('websiteSetup.configuration.sections.1.enabled', false)
                    ->where('websiteSetup.configuration.version', 2),
            );

        $this->get('/dashboard/onboarding/'.$jobId.'/preview')
            ->assertOk()
            ->assertSee('data-template="syifa-care"', false);

        $this->from('/dashboard/onboarding/'.$jobId)
            ->patch('/dashboard/onboarding/'.$jobId, [
                'workspace' => 'website_setup',
                'version' => 2,
                'template_id' => 'ARBITRARY_TEMPLATE',
                'branding' => ['clinic_name' => ''],
                'seo' => [
                    'meta_title' => 'Valid title',
                    'meta_description' => 'Valid description',
                    'meta_keywords' => null,
                    'canonical_url' => 'http://insecure.example.test',
                    'robots_directive' => 'allow-everything',
                    'open_graph_title' => 'Valid sharing title',
                    'open_graph_description' => 'Valid sharing description',
                    'indexing_enabled' => true,
                ],
            ])
            ->assertRedirect('/dashboard/onboarding/'.$jobId)
            ->assertSessionHasErrors([
                'template_id',
                'branding.clinic_name',
                'seo.canonical_url',
                'seo.robots_directive',
                'sections',
            ]);

        $this->get('/dashboard/onboarding/'.$jobId)
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->where('websiteSetup.configuration.template_id', 'SYIFA_CARE')
                    ->where('websiteSetup.configuration.seo.meta_title', 'Klinik Designer SEO')
                    ->where('websiteSetup.configuration.seo.robots_directive', 'index,nofollow')
                    ->where('websiteSetup.configuration.version', 2),
            );
    }

    public function test_clinic_owner_can_open_service_setup_but_other_roles_are_forbidden(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::ClinicOwner,
                'clinic_owner',
                '00000000-0000-4000-8000-000000000002',
            ),
        );

        $this->get(route('dashboard.services'))
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->component('TenantManagement/Booking/ClinicOwnerServiceSetup', false)
                    ->where('pageTitle', 'Service Setup')
                    ->where('navigation.3.key', 'services')
                    ->where('services.0.name', 'Consultation')
                    ->where('services.0.status', 'active')
                    ->where('operationsUrl', route('dashboard.services')),
            );

        foreach ([
            [ActorType::PlatformIdentity, 'website_designer'],
            [ActorType::PlatformIdentity, 'super_admin'],
        ] as [$actorType, $role]) {
            $this->app->instance(AuthorizationService::class, $this->authorization($actorType, $role));
            $this->getJson(route('dashboard.services'))->assertForbidden();
        }
    }

    public function test_assigned_website_designer_uploads_a_website_image_without_request_owned_scope(): void
    {
        $storage = new DashboardWebsiteAssetStorage;
        $this->app->instance(WebsiteAssetBinaryStorageInterface::class, $storage);
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::PlatformIdentity,
                'website_designer',
                identityId: '00000000-0000-4000-8000-000000000010',
            ),
        );
        $jobId = '00000000-0000-4000-8000-000000000101';
        $png = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);

        $this->postJson(route('website-designer.website-assets.store', $jobId), [
            'image' => UploadedFile::fake()->createWithContent('clinic.png', $png),
            'tenant_id' => '00000000-0000-4000-8000-000000000999',
            'website_id' => '00000000-0000-4000-8000-000000000999',
        ])
            ->assertCreated()
            ->assertJsonPath('data.mime_type', 'image/png')
            ->assertJsonPath('data.width', 1)
            ->assertJsonPath('data.height', 1);

        self::assertCount(1, $storage->files);
        self::assertSame(1, $this->websiteRepository->assetCount());

        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::ClinicOwner, 'clinic_owner', 'tenant-1'),
        );
        $this->postJson(route('website-designer.website-assets.store', $jobId), [
            'image' => UploadedFile::fake()->createWithContent('clinic.png', $png),
        ])->assertForbidden();
    }

    public function test_clinic_owner_uploads_a_tenant_owned_website_image(): void
    {
        $storage = new DashboardWebsiteAssetStorage;
        $this->app->instance(WebsiteAssetBinaryStorageInterface::class, $storage);
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::ClinicOwner,
                'clinic_owner',
                '00000000-0000-4000-8000-000000000002',
                '00000000-0000-4000-8000-000000000010',
            ),
        );
        $png = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);

        $response = $this->postJson(route('clinic-owner.website-assets.store'), [
            'image' => UploadedFile::fake()->createWithContent('clinic.png', $png),
            'tenant_id' => 'tenant-2',
            'website_id' => '00000000-0000-4000-8000-000000000999',
        ])
            ->assertCreated()
            ->assertJsonPath('data.mime_type', 'image/png')
            ->assertJsonPath('data.width', 1)
            ->assertJsonPath('data.height', 1);

        self::assertCount(1, $storage->files);
        self::assertSame(1, $this->websiteRepository->assetCount());

        $this->get(route('dashboard.website.content'))
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->where('websiteDraft.mediaUploadUrl', route('clinic-owner.website-assets.store'))
                    ->where(
                        'websiteDraft.assetUrlTemplate',
                        route('public-website.assets.show', '__ASSET_ID__'),
                    ),
            );

        foreach ([
            [ActorType::PlatformIdentity, 'website_designer'],
            [ActorType::PlatformIdentity, 'super_admin'],
        ] as [$actorType, $role]) {
            $this->app->instance(AuthorizationService::class, $this->authorization($actorType, $role));
            $this->postJson(route('clinic-owner.website-assets.store'), [
                'image' => UploadedFile::fake()->createWithContent('clinic.png', $png),
            ])->assertForbidden();
        }
    }

    public function test_website_designer_updates_the_assigned_booking_form_configuration(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::PlatformIdentity,
                'website_designer',
                identityId: '00000000-0000-4000-8000-000000000010',
            ),
        );
        $jobId = '00000000-0000-4000-8000-000000000101';
        $configuration = [
            'workspace' => 'booking_configuration',
            'version' => 1,
            'service_selection_enabled' => true,
            'service_required' => true,
            'email_enabled' => true,
            'email_required' => false,
            'notes_enabled' => true,
            'notes_required' => true,
            'field_order' => [
                'patient_name',
                'phone',
                'service',
                'appointment_date',
                'appointment_time',
                'email',
                'notes',
            ],
            'labels' => [
                'patient_name' => 'Patient name',
                'phone' => 'Phone number',
                'appointment_date' => 'Appointment date',
                'appointment_time' => 'Appointment time',
                'service' => 'Choose a service',
                'email' => 'Email',
                'notes' => 'Additional notes',
            ],
        ];

        $this->patch('/dashboard/onboarding/'.$jobId, $configuration)->assertRedirect();

        $this->get('/dashboard/onboarding/'.$jobId)
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->where('bookingSetup.configuration.version', 2)
                    ->where('bookingSetup.configuration.service_selection_enabled', true)
                    ->where('bookingSetup.configuration.service_required', true)
                    ->where('bookingSetup.configuration.notes_required', true)
                    ->where('bookingSetup.configuration.labels.service', 'Choose a service')
                    ->where('bookingSetup.configuration.active_services.0.name', 'Consultation'),
            );

        $configuration['version'] = 2;
        $configuration['service_selection_enabled'] = false;
        $this->from('/dashboard/onboarding/'.$jobId)
            ->patch('/dashboard/onboarding/'.$jobId, $configuration)
            ->assertRedirect('/dashboard/onboarding/'.$jobId)
            ->assertSessionHasErrors('booking.configuration');

        $this->get('/dashboard/onboarding/'.$jobId)
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->where('bookingSetup.configuration.version', 2)
                    ->where('bookingSetup.configuration.service_selection_enabled', true),
            );
    }

    public function test_website_designer_cannot_mutate_clinic_owned_booking_availability(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::PlatformIdentity,
                'website_designer',
                identityId: '00000000-0000-4000-8000-000000000010',
            ),
        );
        $jobId = '00000000-0000-4000-8000-000000000101';
        $schedule = [
            'workspace' => 'booking_schedule',
            'version' => 1,
            'timezone' => 'Asia/Kuala_Lumpur',
            'appointment_duration_minutes' => 30,
            'booking_capacity_per_slot' => 2,
            'operating_intervals' => [
                ['day' => 1, 'opens_at' => '09:00', 'closes_at' => '17:00'],
                ['day' => 2, 'opens_at' => '09:00', 'closes_at' => '17:00'],
                ['day' => 6, 'opens_at' => '09:00', 'closes_at' => '13:00'],
            ],
        ];

        $this->from('/dashboard/onboarding/'.$jobId)
            ->patch('/dashboard/onboarding/'.$jobId, $schedule)
            ->assertRedirect('/dashboard/onboarding/'.$jobId)
            ->assertSessionHasErrors('workspace');

        $this->get('/dashboard/onboarding/'.$jobId)
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->where('bookingSetup.schedule.version', 1)
                    ->where('bookingSetup.schedule.timezone', 'Asia/Kuala_Lumpur')
                    ->where('bookingSetup.schedule.appointment_duration_minutes', null)
                    ->where('bookingSetup.schedule.booking_capacity_per_slot', null),
            );
    }

    public function test_website_designer_updates_the_assigned_clinic_contact_profile(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::PlatformIdentity,
                'website_designer',
                identityId: '00000000-0000-4000-8000-000000000010',
            ),
        );
        $jobId = '00000000-0000-4000-8000-000000000101';

        $this->patch('/dashboard/onboarding/'.$jobId, [
            'workspace' => 'clinic_contact',
            'version' => 1,
            'operational_phone' => '+60387654321',
            'operational_email' => 'operations@aisyah.test',
            'postal_address' => 'Petaling Jaya, Selangor',
            'whatsapp_number' => '+60198765432',
            'latitude' => 3.1073,
            'longitude' => 101.6067,
        ])->assertRedirect();

        $this->get('/dashboard/onboarding/'.$jobId)
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->where('clinicContact.configuration.version', 2)
                    ->where('clinicContact.configuration.operational_phone', '+60387654321')
                    ->where('clinicContact.configuration.operational_email', 'operations@aisyah.test')
                    ->where('clinicContact.configuration.whatsapp_number', '+60198765432')
                    ->where('clinicContact.configuration.latitude', 3.1073)
                    ->where('clinicContact.configuration.longitude', 101.6067),
            );

        $this->from('/dashboard/onboarding/'.$jobId)
            ->patch('/dashboard/onboarding/'.$jobId, [
                'workspace' => 'clinic_contact',
                'version' => 2,
                'whatsapp_number' => 'https://wa.me/60123456789',
                'latitude' => 3.1073,
                'longitude' => 101.6067,
            ])
            ->assertRedirect('/dashboard/onboarding/'.$jobId)
            ->assertSessionHasErrors('clinic_contact.configuration');

        $this->from('/dashboard/onboarding/'.$jobId)
            ->patch('/dashboard/onboarding/'.$jobId, [
                'workspace' => 'clinic_contact',
                'version' => 2,
                'latitude' => 91,
                'longitude' => 181,
            ])
            ->assertRedirect('/dashboard/onboarding/'.$jobId)
            ->assertSessionHasErrors(['latitude', 'longitude']);
    }

    public function test_unassigned_job_is_not_disclosed_and_other_roles_are_forbidden(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::PlatformIdentity, 'website_designer'),
        );
        $this->get('/dashboard/onboarding/00000000-0000-4000-8000-000000000999')
            ->assertNotFound();

        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::ClinicOwner, 'clinic_owner', 'tenant-1'),
        );
        $this->getJson('/dashboard/onboarding/00000000-0000-4000-8000-000000000101')
            ->assertForbidden();
        $this->patchJson('/dashboard/onboarding/00000000-0000-4000-8000-000000000101')
            ->assertForbidden();
    }

    public function test_assigned_designer_can_complete_accountable_task_and_other_roles_cannot_use_designer_route(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::PlatformIdentity,
                'website_designer',
                identityId: '00000000-0000-4000-8000-000000000010',
            ),
        );
        $this->patchJson(
            '/dashboard/onboarding/00000000-0000-4000-8000-000000000101/tasks/00000000-0000-4000-8000-000000000106',
            [
                'operation' => 'complete',
                'expected_version' => 1,
                'evidence_reference' => 'website_configuration_reviewed',
            ],
        )
            ->assertOk()
            ->assertJsonPath('version', 2);

        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::ClinicOwner, 'clinic_owner', 'tenant-1'),
        );
        $this->patchJson(
            '/dashboard/onboarding/00000000-0000-4000-8000-000000000101/tasks/00000000-0000-4000-8000-000000000106',
            [
                'operation' => 'complete',
                'expected_version' => 2,
                'evidence_reference' => 'forbidden',
            ],
        )->assertForbidden();
    }

    public function test_clinic_owner_receives_the_website_overview_from_application_providers(): void
    {
        $this->websiteAddresses->seed(
            new WebsitePublicAddressData(
                'website-1',
                'tenant-1',
                'klinik-aisyah.syifa.my',
                'https://klinik-aisyah.syifa.my',
                true,
            ),
        );
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::ClinicOwner, 'clinic_owner', 'tenant-1'),
        );

        $this->get('/dashboard/website')
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->component('TenantManagement/Website/ClinicOwnerWebsiteOverview', false)
                    ->where('websiteStatus.value', 'Published')
                    ->where('publishStatus.value', 'Published')
                    ->where('domainStatus.value', 'Live')
                    ->where('domainStatus.detail', 'klinik-aisyah.syifa.my')
                    ->where('domainStatus.url', 'https://klinik-aisyah.syifa.my')
                    ->where('domainStatus.actionLabel', 'Open Published Website')
                    ->where('themeInformation.value', 'syifa-essential')
                    ->where('seoStatus.value', 'Indexing enabled')
                    ->has('quickActions', 1)
                    ->where('quickActions.0.key', 'edit')
                    ->where('quickActions.0.available', true)
                    ->where('quickActions.0.href', route('dashboard.website.content'))
                    ->has('navigation', 8),
            );
    }

    public function test_clinic_owner_receives_the_website_content_overview_from_published_query_evidence(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::ClinicOwner, 'clinic_owner', '00000000-0000-4000-8000-000000000002', '00000000-0000-4000-8000-000000000010'),
        );

        $this->get('/dashboard/website/content')
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->component('TenantManagement/Website/ClinicOwnerWebsiteContentOverview', false)
                    ->where('contentHealth.value', '8 of 8 complete')
                    ->has('contentSections', 8)
                    ->where('contentSections.0.title', 'Homepage')
                    ->where('contentSections.0.complete', true)
                    ->where('contentSections.0.detail', 'Trusted healthcare')
                    ->where('contentSections.2.title', 'Services')
                    ->where('contentSections.2.itemCount', 3)
                    ->where('contentSections.2.detail', 'Primary care · Vaccination · General practice')
                    ->where('editableContent.branding.clinic_name', 'Klinik Aisyah')
                    ->where('editableContent.version', 1)
                    ->has('templateOptions', 5)
                    ->where('canChangeTemplate', true)
                    ->where('previewUrl', route('dashboard.website.preview'))
                    ->where('updateUrl', route('dashboard.website.content.update'))
                    ->where(
                        'websiteDraft.mediaUploadUrl',
                        route('clinic-owner.website-assets.store'),
                    )
                    ->where(
                        'websiteDraft.assetUrlTemplate',
                        route('public-website.assets.show', '__ASSET_ID__'),
                    )
                    ->has('navigation', 8),
            );
    }

    public function test_clinic_owner_updates_their_current_website_configuration(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::ClinicOwner, 'clinic_owner', '00000000-0000-4000-8000-000000000002', '00000000-0000-4000-8000-000000000010'),
        );

        $this->patch('/dashboard/website/content', [
            'version' => 1,
            'template_id' => 'SYIFA_CARE',
            'branding' => [
                'clinic_name' => 'Klinik Baharu',
                'tagline' => 'Trusted care',
                'primary_color' => '#aabbcc',
                'secondary_color' => '#ddeeff',
                'logo_reference' => null,
                'logo_display_size' => 'large',
                'contact_email' => 'hello@example.test',
                'contact_phone' => '+60123456789',
                'address' => 'Kuala Lumpur',
                'social_links' => ['facebook' => 'https://facebook.com/klinik'],
            ],
            'seo' => [
                'meta_title' => 'Klinik Baharu',
                'meta_description' => 'Trusted family healthcare.',
                'meta_keywords' => null,
                'canonical_url' => 'https://clinic.example.test',
                'robots_directive' => 'index,follow',
                'open_graph_title' => 'Klinik Baharu',
                'open_graph_description' => 'Trusted family healthcare.',
                'indexing_enabled' => true,
            ],
            'sections' => [
                'hero' => true,
                'about' => true,
                'services' => true,
                'doctors' => true,
                'testimonials' => true,
                'gallery' => true,
                'faq' => true,
                'contact' => true,
                'booking_cta' => false,
            ],
        ])->assertRedirect();

        $this->get('/dashboard/website/content')
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->where('editableContent.branding.clinic_name', 'Klinik Baharu')
                    ->where('editableContent.template_id', 'SYIFA_CARE')
                    ->where('editableContent.branding.primary_color', '#AABBCC')
                    ->where('editableContent.branding.secondary_color', '#DDEEFF')
                    ->where('editableContent.branding.logo_display_size', 'large')
                    ->where('editableContent.sections.8.enabled', false)
                    ->where('editableContent.version', 2),
            );
    }

    public function test_clinic_owner_navigation_remains_consistent_across_every_portal_page(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::ClinicOwner, 'clinic_owner', '00000000-0000-4000-8000-000000000002', '00000000-0000-4000-8000-000000000010'),
        );

        foreach (['/dashboard', '/dashboard/website', '/dashboard/website/content', '/dashboard/services', '/dashboard/bookings'] as $uri) {
            $this->get($uri)
                ->assertOk()
                ->assertInertia(
                    static fn (AssertableInertia $page): AssertableInertia => $page
                        ->where('navigation.0.key', 'dashboard')
                        ->where('navigation.1.key', 'website')
                        ->where('navigation.2.key', 'content')
                        ->where('navigation.3.key', 'services')
                        ->where('navigation.4.key', 'bookings')
                        ->where('navigation.5.key', 'subscription')
                        ->where('navigation.6.key', 'notifications')
                        ->where('navigation.7.key', 'reports')
                        ->has('navigation', 8),
                );
        }
    }

    public function test_only_the_assigned_website_designer_can_open_the_custom_domain_add_on_workspace(): void
    {
        $jobId = '00000000-0000-4000-8000-000000000101';
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::PlatformIdentity,
                'website_designer',
                identityId: '00000000-0000-4000-8000-000000000010',
            ),
        );

        $this->get('/dashboard/onboarding/'.$jobId.'/custom-domain')
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->component('PlatformAdministration/Onboarding/WebsiteDesignerCustomDomain', false)
                    ->where('domain', null)
                    ->where(
                        'operationsUrl',
                        route('dashboard.onboarding.custom-domain', ['jobId' => $jobId]),
                    )
                    ->where('job.tenantId', '00000000-0000-4000-8000-000000000002')
                    ->where('job.websiteId', '00000000-0000-4000-8000-000000000001'),
            );

        $this->post('/dashboard/onboarding/'.$jobId.'/custom-domain', [
            'hostname' => 'www.klinik-aisyah.my',
        ])->assertRedirect();
        $this->get('/dashboard/onboarding/'.$jobId.'/custom-domain')
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->where('domain.hostname', 'www.klinik-aisyah.my')
                    ->where('domain.status', 'verification_pending')
                    ->where('domain.version', 1)
                    ->where('domain.verificationValue', static fn (mixed $value): bool => is_string($value)
                        && str_starts_with($value, 'syifa-verification=')),
            );

        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::ClinicOwner,
                'clinic_owner',
                '00000000-0000-4000-8000-000000000002',
            ),
        );

        $this->getJson('/dashboard/onboarding/'.$jobId.'/custom-domain')->assertForbidden();
        $this->get('/dashboard/website/domain')->assertNotFound();
    }

    public function test_clinic_owner_receives_the_booking_overview_from_application_providers(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::ClinicOwner,
                'clinic_owner',
                '00000000-0000-4000-8000-000000000002',
                '00000000-0000-4000-8000-000000000020',
            ),
        );

        $this->get('/dashboard/bookings?search=BOOK&status=submitted&source=WEBSITE&per_page=10')
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->component('TenantManagement/Booking/ClinicOwnerBookingOverview', false)
                    ->where('bookingList.items.0.reference', 'BOOK-001')
                    ->where('bookingList.search.value', 'BOOK')
                    ->where('bookingList.filters.status.value', 'submitted')
                    ->where('bookingList.filters.source.value', 'WEBSITE')
                    ->where('bookingList.pagination.perPage', 10)
                    ->where('statusSummary.total', 1)
                    ->where('sourceSummary.items.0.count', 1)
                    ->where('manualBooking.storeUrl', route('dashboard.bookings.store'))
                    ->where('manualBooking.sources.0.value', 'phone')
                    ->where('manualBooking.serviceSelectionEnabled', true)
                    ->where('manualBooking.services.0.name', 'Consultation')
                    ->where('bookingSchedule.updateUrl', route('dashboard.bookings.schedule.update'))
                    ->where('bookingSchedule.businessHoursUpdateUrl', route('dashboard.bookings.business-hours.update'))
                    ->where('bookingSchedule.timezone', 'Asia/Kuala_Lumpur')
                    ->has('navigation', 8),
            );
    }

    public function test_clinic_owner_can_update_the_authoritative_booking_schedule(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::ClinicOwner,
                'clinic_owner',
                '00000000-0000-4000-8000-000000000002',
                '00000000-0000-4000-8000-000000000020',
            ),
        );

        $this->patch(route('dashboard.bookings.schedule.update'), [
            'version' => 1,
            'timezone' => 'Asia/Kuala_Lumpur',
            'appointment_duration_minutes' => 30,
            'booking_capacity_per_slot' => 2,
            'operating_intervals' => [
                ['day' => 1, 'opens_at' => '09:00', 'closes_at' => '17:00'],
                ['day' => 2, 'opens_at' => '09:00', 'closes_at' => '17:00'],
            ],
        ])->assertRedirect();

        $clinic = $this->app->make(ClinicRepositoryInterface::class)->findByTenantId(
            new TenantId('00000000-0000-4000-8000-000000000002'),
        );
        self::assertNotNull($clinic);
        self::assertSame('09:00', $clinic->weeklyOperatingHours()->all()[1][0]->opensAt->value);
        self::assertSame('09:00', $clinic->weeklyBookingAvailability()->all()[1][0]->opensAt->value);
        self::assertSame([], $clinic->weeklyBookingAvailability()->all()[6]);

        $this->get(route('dashboard.bookings'))
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->where('bookingSchedule.version', 2)
                    ->where('bookingSchedule.appointment_duration_minutes', 30)
                    ->where('bookingSchedule.booking_capacity_per_slot', 2)
                    ->where('bookingSchedule.operating_intervals.0.day', 1)
                    ->where('bookingSchedule.operating_intervals.0.opens_at', '09:00')
                    ->where('bookingSchedule.operating_intervals.0.closes_at', '17:00'),
            );
    }

    public function test_non_clinic_owner_cannot_update_a_clinic_booking_schedule(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::PlatformIdentity,
                'website_designer',
                null,
                '00000000-0000-4000-8000-000000000010',
            ),
        );

        $this->patch(route('dashboard.bookings.schedule.update'), [
            'version' => 1,
            'timezone' => 'Asia/Kuala_Lumpur',
            'appointment_duration_minutes' => 30,
            'booking_capacity_per_slot' => 1,
            'operating_intervals' => [
                ['day' => 1, 'opens_at' => '09:00', 'closes_at' => '17:00'],
            ],
        ])->assertForbidden();
    }

    public function test_only_clinic_owner_can_create_and_remove_a_booking_date_override(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::ClinicOwner,
                'clinic_owner',
                '00000000-0000-4000-8000-000000000002',
                '00000000-0000-4000-8000-000000000020',
            ),
        );

        $this->from(route('dashboard.bookings'))->post(route('dashboard.bookings.date-overrides.store'), [
            'local_date' => '2099-08-10',
            'closed' => true,
            'version' => 0,
        ])->assertRedirect(route('dashboard.bookings'))->assertSessionHasNoErrors();

        $this->get(route('dashboard.bookings'))->assertOk()->assertInertia(
            static fn (AssertableInertia $page): AssertableInertia => $page
                ->where('bookingSchedule.dateOverrides.0.local_date', '2099-08-10')
                ->where('bookingSchedule.dateOverrides.0.closed', true)
                ->where('bookingSchedule.dateOverrides.0.version', 1),
        );

        $this->from(route('dashboard.bookings'))->delete(
            route('dashboard.bookings.date-overrides.destroy', ['localDate' => '2099-08-10']),
            ['version' => 1],
        )->assertRedirect(route('dashboard.bookings'))->assertSessionHasNoErrors();

        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::PlatformIdentity, 'website_designer', null, '00000000-0000-4000-8000-000000000010'),
        );
        $this->post(route('dashboard.bookings.date-overrides.store'), [
            'local_date' => '2099-08-11',
            'closed' => true,
            'version' => 0,
        ])->assertForbidden();
    }

    public function test_clinic_owner_can_update_business_hours_without_changing_booking_hours(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::ClinicOwner,
                'clinic_owner',
                '00000000-0000-4000-8000-000000000002',
                '00000000-0000-4000-8000-000000000020',
            ),
        );

        $this->from(route('dashboard.bookings'))->patch(route('dashboard.bookings.business-hours.update'), [
            'version' => 1,
            'timezone' => 'Asia/Kuala_Lumpur',
            'operating_intervals' => [
                ['day' => 1, 'opens_at' => '08:00', 'closes_at' => '18:00'],
                ['day' => 2, 'opens_at' => '09:00', 'closes_at' => '13:00'],
                ['day' => 2, 'opens_at' => '14:00', 'closes_at' => '18:00'],
            ],
        ])->assertRedirect(route('dashboard.bookings'))
            ->assertSessionHasNoErrors();

        $clinic = $this->app->make(ClinicRepositoryInterface::class)->findByTenantId(
            new TenantId('00000000-0000-4000-8000-000000000002'),
        );
        self::assertNotNull($clinic);
        self::assertSame('08:00', $clinic->weeklyOperatingHours()->all()[1][0]->opensAt->value);
        self::assertCount(2, $clinic->weeklyOperatingHours()->all()[2]);
        self::assertSame('09:00', $clinic->weeklyBookingAvailability()->all()[1][0]->opensAt->value);
    }

    public function test_clinic_owner_can_configure_multiple_booking_sessions_independently_of_business_hours(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(
                ActorType::ClinicOwner,
                'clinic_owner',
                '00000000-0000-4000-8000-000000000002',
                '00000000-0000-4000-8000-000000000020',
            ),
        );

        $this->from(route('dashboard.bookings'))->patch(route('dashboard.bookings.schedule.update'), [
            'version' => 1,
            'timezone' => 'Asia/Kuala_Lumpur',
            'appointment_duration_minutes' => 30,
            'booking_capacity_per_slot' => 1,
            'operating_intervals' => [
                ['day' => 1, 'opens_at' => '09:00', 'closes_at' => '12:00'],
                ['day' => 1, 'opens_at' => '15:00', 'closes_at' => '17:00'],
                ['day' => 1, 'opens_at' => '20:00', 'closes_at' => '21:00'],
            ],
        ])->assertRedirect(route('dashboard.bookings'))
            ->assertSessionHasNoErrors();

        $clinic = $this->app->make(ClinicRepositoryInterface::class)->findByTenantId(
            new TenantId('00000000-0000-4000-8000-000000000002'),
        );
        self::assertNotNull($clinic);
        self::assertSame(2, $clinic->version());
        self::assertSame('09:00', $clinic->weeklyOperatingHours()->all()[1][0]->opensAt->value);
        self::assertCount(3, $clinic->weeklyBookingAvailability()->all()[1]);
        self::assertSame('20:00', $clinic->weeklyBookingAvailability()->all()[1][2]->opensAt->value);
        self::assertSame('21:00', $clinic->weeklyBookingAvailability()->all()[1][2]->closesAt->value);
    }

    public function test_clinic_owner_receives_tenant_scoped_booking_detail_and_history(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::ClinicOwner, 'clinic_owner', 'tenant-1'),
        );
        $bookingId = '00000000-0000-4000-8000-000000000001';

        $this->get(route('dashboard.bookings.show', ['bookingId' => $bookingId]))
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->component('TenantManagement/Booking/ClinicOwnerBookingDetail', false)
                    ->where('booking.reference', 'BOOK-001')
                    ->where('booking.patientName', 'Patient Name')
                    ->where('booking.serviceName', 'Consultation')
                    ->where('booking.sourceLabel', 'Website')
                    ->where('history.0.actorType', 'public_visitor')
                    ->where('history.0.payload.source', 'WEBSITE'),
            );

        $this->get('/dashboard/bookings/00000000-0000-4000-8000-000000000099')
            ->assertNotFound();
    }

    public function test_booking_detail_and_mutations_remain_clinic_owner_only_and_refresh_detail(): void
    {
        $bookingId = '00000000-0000-4000-8000-000000000001';
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::ClinicOwner, 'clinic_owner', 'tenant-1'),
        );

        $this->post(route('dashboard.bookings.confirm', ['bookingId' => $bookingId]), [
            'return_to_detail' => true,
        ])->assertStatus(303)
            ->assertRedirect(route('dashboard.bookings.show', ['bookingId' => $bookingId]));
        $this->patch(route('dashboard.bookings.reschedule', ['bookingId' => $bookingId]), [
            'appointment_date' => '2026-09-02',
            'appointment_time' => '10:30',
            'return_to_detail' => true,
        ])->assertStatus(303)
            ->assertRedirect(route('dashboard.bookings.show', ['bookingId' => $bookingId]));
        $this->post(route('dashboard.bookings.cancel', ['bookingId' => $bookingId]), [
            'return_to_detail' => true,
        ])->assertStatus(303)
            ->assertRedirect(route('dashboard.bookings.show', ['bookingId' => $bookingId]));
        $this->post(route('dashboard.bookings.complete', ['bookingId' => $bookingId]), [
            'return_to_detail' => true,
        ])->assertStatus(303)
            ->assertRedirect(route('dashboard.bookings.show', ['bookingId' => $bookingId]));

        self::assertSame([
            ['confirm', 'tenant-1', $bookingId, 'identity-1', 'clinic_owner'],
            ['reschedule', 'tenant-1', $bookingId, '2026-09-02', '10:30', 'identity-1', 'clinic_owner'],
            ['cancel', 'tenant-1', $bookingId, 'identity-1', 'clinic_owner'],
            ['complete', 'tenant-1', $bookingId, 'identity-1', 'clinic_owner'],
        ], $this->bookingOperations->calls);

        foreach (['website_designer', 'super_admin'] as $role) {
            $this->app->instance(
                AuthorizationService::class,
                $this->authorization(ActorType::PlatformIdentity, $role),
            );
            $this->getJson(route('dashboard.bookings.show', ['bookingId' => $bookingId]))
                ->assertForbidden();
        }
    }

    public function test_manual_booking_delivery_validates_approved_sources_and_remains_clinic_owner_only(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::ClinicOwner, 'clinic_owner', 'tenant-1'),
        );

        $this->post(route('dashboard.bookings.store'), [
            'source' => 'telegram',
            'patient_name' => 'Aisyah',
            'phone' => '+6012',
            'appointment_date' => '2026-09-02',
            'appointment_time' => '10:30',
        ])->assertSessionHasErrors('source');

        foreach (['website_designer', 'super_admin'] as $role) {
            $this->app->instance(
                AuthorizationService::class,
                $this->authorization(ActorType::PlatformIdentity, $role),
            );

            $this->postJson(route('dashboard.bookings.store'), [
                'source' => 'phone',
                'patient_name' => 'Aisyah',
                'phone' => '+6012',
                'appointment_date' => '2026-09-02',
                'appointment_time' => '10:30',
            ])->assertForbidden();
        }
    }

    public function test_platform_identity_cannot_access_the_clinic_owner_website_overview(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::PlatformIdentity, 'website_designer'),
        );

        $this->getJson('/dashboard/website')->assertForbidden();
        $this->getJson('/dashboard/website/content')->assertForbidden();
        $this->getJson('/dashboard/bookings')->assertForbidden();
    }

    public function test_clinic_owner_can_confirm_cancel_and_reschedule_through_booking_operations(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::ClinicOwner, 'clinic_owner', 'tenant-1'),
        );
        $bookingId = '00000000-0000-4000-8000-000000000001';

        $this->post("/dashboard/bookings/{$bookingId}/confirm")
            ->assertStatus(303)
            ->assertRedirect(route('dashboard.bookings'));
        $this->post("/dashboard/bookings/{$bookingId}/cancel")
            ->assertStatus(303)
            ->assertRedirect(route('dashboard.bookings'));
        $this->patch("/dashboard/bookings/{$bookingId}/reschedule", [
            'appointment_date' => '2026-09-02',
            'appointment_time' => '10:30',
        ])->assertStatus(303)->assertRedirect(route('dashboard.bookings'));

        self::assertSame([
            ['confirm', 'tenant-1', $bookingId, 'identity-1', 'clinic_owner'],
            ['cancel', 'tenant-1', $bookingId, 'identity-1', 'clinic_owner'],
            ['reschedule', 'tenant-1', $bookingId, '2026-09-02', '10:30', 'identity-1', 'clinic_owner'],
        ], $this->bookingOperations->calls);
    }

    public function test_reschedule_rejects_malformed_schedule_without_invoking_booking_engine(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::ClinicOwner, 'clinic_owner', 'tenant-1'),
        );

        $this->patch('/dashboard/bookings/00000000-0000-4000-8000-000000000001/reschedule', [
            'appointment_date' => 'tomorrow',
            'appointment_time' => 'later',
        ])->assertSessionHasErrors(['appointment_date', 'appointment_time']);

        self::assertSame([], $this->bookingOperations->calls);
    }

    public function test_public_visitor_cannot_receive_the_authenticated_shell(): void
    {
        $this->app->instance(AuthorizationService::class, $this->authorization());

        $this->getJson('/dashboard')
            ->assertForbidden()
            ->assertJsonPath('type', 'forbidden');
    }

    /**
     * @return iterable<string, array{ActorType, string, ?string, string}>
     */
    public static function authenticatedActors(): iterable
    {
        yield 'clinic owner' => [
            ActorType::ClinicOwner,
            'clinic_owner',
            'tenant-1',
            'TenantManagement/Dashboard/ClinicOwnerDashboardOverview',
        ];
        yield 'website designer' => [
            ActorType::PlatformIdentity,
            'website_designer',
            null,
            'PlatformAdministration/Dashboard/WebsiteDesignerDashboardOverview',
        ];
        yield 'super admin' => [
            ActorType::PlatformIdentity,
            'super_admin',
            null,
            'PlatformAdministration/Dashboard/SuperAdminDashboardOverview',
        ];
    }

    private function authorization(
        ?ActorType $actorType = null,
        ?string $role = null,
        ?string $tenantId = null,
        string $identityId = 'identity-1',
    ): AuthorizationService {
        $identity = $actorType === null || $role === null
            ? null
            : new AuthenticatedIdentity(
                $actorType,
                $identityId,
                $tenantId,
                $role,
                'Authenticated User',
            );

        return new AuthorizationService(
            new class($identity) implements CurrentUserInterface
            {
                public function __construct(private readonly ?AuthenticatedIdentityInterface $identity) {}

                public function resolve(): ?AuthenticatedIdentityInterface
                {
                    return $this->identity;
                }
            },
            new class($role) implements RoleResolverInterface
            {
                public function __construct(private readonly ?string $role) {}

                public function currentRole(): ?string
                {
                    return $this->role;
                }
            },
            new class implements PermissionResolverInterface
            {
                public function can(string $categoryKey, string $permissionKey): bool
                {
                    return false;
                }
            },
        );
    }
}

final class DashboardEmptyCustomDomainRepository implements CustomDomainRepositoryInterface
{
    private ?CustomDomain $domain = null;

    public function currentForWebsite(string $tenantId, string $websiteId): ?CustomDomain
    {
        return $this->domain !== null
            && $this->domain->tenantId === $tenantId
            && $this->domain->websiteId === $websiteId
            && $this->domain->status()->value !== 'detached'
                ? $this->domain
                : null;
    }

    public function findOwned(string $tenantId, string $domainId): ?CustomDomain
    {
        return $this->domain !== null
            && $this->domain->tenantId === $tenantId
            && $this->domain->id === $domainId
                ? $this->domain
                : null;
    }

    public function save(CustomDomain $domain): void
    {
        if ($domain->version() === 0) {
            $domain->synchronizeVersion(1);
        } else {
            $domain->synchronizeVersion($domain->version() + 1);
        }
        $this->domain = $domain;
    }
}

final class DashboardOnboardingJobRepository implements OnboardingJobRepositoryInterface
{
    public function __construct(private OnboardingJob $job) {}

    public function find(OnboardingTenantId $tenantId, OnboardingJobId $onboardingJobId): ?OnboardingJob
    {
        return $this->job->tenantId->value === $tenantId->value
            && $this->job->id->value === $onboardingJobId->value
                ? $this->job
                : null;
    }

    public function findById(OnboardingJobId $onboardingJobId): ?OnboardingJob
    {
        return $this->job->id->value === $onboardingJobId->value ? $this->job : null;
    }

    public function save(OnboardingJob $onboardingJob): void
    {
        $onboardingJob->synchronizePersistenceVersion($onboardingJob->version() + 1);
        $this->job = $onboardingJob;
    }
}

final readonly class DashboardOnboardingWorkflowTransaction implements OnboardingWorkflowTransactionInterface
{
    public function run(callable $operation): mixed
    {
        return $operation();
    }
}

final readonly class DashboardWebsiteApprovalAudit implements WebsiteApprovalAuditInterface
{
    public function recordWebsiteApprovalRequested(
        string $actorId,
        string $tenantId,
        string $jobId,
        string $approvalId,
        int $websiteVersion,
        int $draftVersion,
        int $resultingJobVersion,
        string $correlationId,
        DateTimeImmutable $occurredAt,
    ): void {}

    public function recordWebsiteApprovalDecision(
        string $actorId,
        string $tenantId,
        string $jobId,
        string $approvalId,
        string $decision,
        int $resultingJobVersion,
        string $correlationId,
        DateTimeImmutable $occurredAt,
    ): void {}
}

final readonly class DashboardOnboardingAudit implements OnboardingAuditInterface
{
    public function recordDesignerAssignment(
        string $auditEntryId,
        string $actorPlatformIdentityId,
        string $tenantId,
        string $jobId,
        string $assignmentId,
        string $designerId,
        int $resultingVersion,
        string $correlationId,
        DateTimeImmutable $occurredAt,
    ): void {}

    public function recordDesignerReassignment(
        string $auditEntryId,
        string $actorPlatformIdentityId,
        string $tenantId,
        string $jobId,
        string $previousAssignmentId,
        string $newAssignmentId,
        string $designerId,
        int $previousVersion,
        int $resultingVersion,
        string $correlationId,
        DateTimeImmutable $occurredAt,
    ): void {}

    public function recordJobLifecycleChange(
        string $actorPlatformIdentityId,
        string $tenantId,
        string $jobId,
        string $operation,
        ?string $reason,
        int $previousVersion,
        int $resultingVersion,
        string $correlationId,
        DateTimeImmutable $occurredAt,
    ): void {}

    public function recordTaskWaiver(
        string $actorPlatformIdentityId,
        string $tenantId,
        string $jobId,
        string $taskId,
        string $reason,
        int $previousVersion,
        int $resultingVersion,
        string $correlationId,
        DateTimeImmutable $occurredAt,
    ): void {}
}

final readonly class DashboardApprovedWebsitePublication implements WebsitePublicationApprovalReadInterface
{
    public function isApproved(
        string $tenantId,
        string $websiteId,
        int $websiteVersion,
        int $draftVersion,
    ): bool {
        return true;
    }
}

final readonly class DashboardOnboardingTaskRead implements OnboardingTaskReadInterface
{
    public function forTenant(string $tenantId): ?array
    {
        return null;
    }
}

final readonly class DashboardLaunchReadinessRead implements LaunchReadinessReadInterface
{
    public const string JOB_ID = '00000000-0000-4000-8000-000000000101';

    public const string TENANT_ID = '00000000-0000-4000-8000-000000000002';

    public function forJob(string $onboardingJobId): ?LaunchReadinessData
    {
        return $onboardingJobId === self::JOB_ID ? $this->data() : null;
    }

    public function forTenant(string $tenantId): ?LaunchReadinessData
    {
        return $tenantId === self::TENANT_ID ? $this->data() : null;
    }

    public function forJobs(array $onboardingJobIds): array
    {
        return in_array(self::JOB_ID, $onboardingJobIds, true)
            ? [self::JOB_ID => $this->data()]
            : [];
    }

    private function data(): LaunchReadinessData
    {
        return new LaunchReadinessData(
            self::JOB_ID,
            self::TENANT_ID,
            '00000000-0000-4000-8000-000000000001',
            'blocked',
            [[
                'key' => 'clinic_owner_approval',
                'label' => 'Clinic Owner approval',
                'satisfied' => false,
                'detail' => 'Current approval is required.',
            ]],
        );
    }
}

final readonly class DashboardClinicOwnerWebsiteApprovalRead implements ClinicOwnerWebsiteApprovalReadInterface
{
    public function forTenant(string $tenantId): ?array
    {
        return null;
    }
}

final readonly class DashboardFixedClinicSummary implements ClinicSummaryReadInterface
{
    public function summary(string $trustedTenantId): ?ClinicSummaryData
    {
        return new ClinicSummaryData('clinic-1', 'Klinik Syifa', 'Asia/Kuala_Lumpur', true);
    }
}

final readonly class DashboardFixedPlatformRead implements PlatformDashboardReadInterface
{
    public function overview(): PlatformDashboardData
    {
        return new PlatformDashboardData(
            12, 9, 3, 5, 8, 42, true,
            [new PlatformDashboardActivityData(
                'audit-1',
                'tenant.activated',
                'success',
                new DateTimeImmutable('2026-08-25T10:00:00+08:00'),
            )],
        );
    }
}

final readonly class DashboardFixedTenantOverviewRead implements TenantOverviewReadInterface
{
    public function list(?string $status, ?string $cursor, int $limit, ?string $search): array
    {
        return [
            new TenantOverviewData(
                'tenant-1',
                'Klinik Aisyah',
                'Aisyah Rahman',
                'aisyah@example.test',
                'active',
                'active',
                true,
                'Designer One',
            ),
        ];
    }
}

final readonly class DashboardFixedBillingOverviewRead implements BillingOverviewReadInterface
{
    public function summary(string $asOfDate): BillingOverviewData
    {
        return new BillingOverviewData(
            8, 2, 3, 123456, 'MYR',
            [new RecentPaymentData('payment-1', 'tenant-1', 10000, 'MYR', 'succeeded', '2026-08-25')],
            1, 10, 2, 0,
        );
    }

    public function subscriptions(?string $status, ?string $cursor, int $limit, ?string $search): array
    {
        return [
            new SubscriptionOverviewData(
                'subscription-1', 'tenant-1', 'essential', 'annual',
                120000, 'MYR', '2026-01-01', '2026-12-31', 'active',
            ),
        ];
    }
}

final readonly class DashboardFixedSubscriptionDetailRead implements PaymentHistoryReadInterface, SubscriptionDetailReadInterface, SubscriptionTimelineReadInterface
{
    public function detail(string $subscriptionId): ?SubscriptionDetailData
    {
        return new SubscriptionDetailData(
            $subscriptionId,
            'tenant-1',
            'essential',
            'annual',
            120000,
            'MYR',
            '2026-01-01',
            '2026-12-31',
            'renewal_due',
            'due',
            'not_configured',
            2,
            '22222222-2222-4222-8222-222222222222',
        );
    }

    public function list(string $subscriptionId, ?string $cursor, int $limit): array
    {
        return [new SubscriptionTimelineData('event-1', 'renewal_due', '2026-12-01')];
    }

    public function listForSubscription(string $subscriptionId, ?string $cursor, int $limit): array
    {
        return [new SubscriptionPaymentData('payment-1', 'initial_activation', 120000, 'MYR', 'succeeded', '2026-01-01')];
    }
}

final class DashboardRecordedSubscriptionOperations implements CancelAutoRenewInterface, EnableAutoRenewInterface, ManualRenewSubscriptionInterface
{
    public function renew(ManualRenewSubscriptionCommand $command): RenewalOperationResult
    {
        return new RenewalOperationResult('accepted', 'renewal-1');
    }

    public function enable(AutoRenewCommand $command): AutoRenewOperationResult
    {
        return new AutoRenewOperationResult('enabled', $command->expectedVersion + 1);
    }

    public function cancel(AutoRenewCommand $command): AutoRenewOperationResult
    {
        return new AutoRenewOperationResult('cancelled', $command->expectedVersion + 1);
    }
}

final readonly class DashboardFixedDesignerRead implements WebsiteDesignerDashboardReadInterface
{
    public function forPlatformIdentity(string $platformIdentityId): WebsiteDesignerDashboardData
    {
        return new WebsiteDesignerDashboardData(
            assignedJobs: 3,
            pendingContentCollection: 1,
            websiteSetup: 1,
            reviewAndRevision: 1,
            readyToPublish: 0,
            completedProjects: 2,
            recentAssignments: [
                new WebsiteDesignerRecentAssignmentData(
                    'assignment-1',
                    'job-1',
                    'tenant-1',
                    'in_progress',
                    new DateTimeImmutable('2026-08-24T09:30:00+08:00'),
                ),
            ],
        );
    }

    public function queue(string $platformIdentityId, ?string $status, ?string $cursor, int $limit, ?string $search): array
    {
        return [
            new WebsiteDesignerQueueJobData(
                'assignment-1',
                'job-1',
                'tenant-1',
                'website-1',
                'in_progress',
                new DateTimeImmutable('2026-08-24T09:30:00+08:00'),
                new DateTimeImmutable('2026-08-25T10:00:00+08:00'),
            ),
        ];
    }

    public function detail(string $platformIdentityId, string $onboardingJobId): ?WebsiteDesignerJobDetailData
    {
        if ($platformIdentityId !== '00000000-0000-4000-8000-000000000010'
            || $onboardingJobId !== '00000000-0000-4000-8000-000000000101') {
            return null;
        }

        return new WebsiteDesignerJobDetailData(
            'assignment-1',
            $onboardingJobId,
            '00000000-0000-4000-8000-000000000002',
            '00000000-0000-4000-8000-000000000001',
            'in_progress',
            1,
            new DateTimeImmutable('2026-08-24T09:30:00+08:00'),
            new DateTimeImmutable('2026-08-25T10:00:00+08:00'),
            [
                'job_created_at' => new DateTimeImmutable('2026-08-20T09:00:00+08:00'),
                'assigned_at' => new DateTimeImmutable('2026-08-24T09:30:00+08:00'),
                'in_progress_at' => new DateTimeImmutable('2026-08-25T10:00:00+08:00'),
            ],
        );
    }
}

final readonly class DashboardFixedSubscriptionSummary implements SubscriptionSummaryReadInterface
{
    public function summary(string $trustedTenantId): ?SubscriptionSummaryData
    {
        return new SubscriptionSummaryData('active', '2027-08-20');
    }
}

final readonly class DashboardInactiveSubscriptionSummary implements SubscriptionSummaryReadInterface
{
    public function summary(string $trustedTenantId): ?SubscriptionSummaryData
    {
        return new SubscriptionSummaryData('expired', '2026-01-01');
    }
}

final class DashboardWebsitePublicAddressRepository implements WebsitePublicAddressRepositoryInterface
{
    private ?WebsitePublicAddressData $address = null;

    public function seed(WebsitePublicAddressData $address): void
    {
        $this->address = $address;
    }

    public function isAvailable(string $normalizedHost, string $websiteId): bool
    {
        return $this->address === null
            || $this->address->host === $normalizedHost
            || $this->address->websiteId === $websiteId;
    }

    public function reservePrimary(
        string $addressId,
        string $trustedTenantId,
        string $websiteId,
        string $normalizedHost,
        DateTimeImmutable $at,
    ): WebsitePublicAddressData {
        return $this->address = new WebsitePublicAddressData(
            $websiteId,
            $trustedTenantId,
            $normalizedHost,
            'https://'.$normalizedHost,
            false,
        );
    }

    public function activatePrimary(
        string $trustedTenantId,
        string $websiteId,
        DateTimeImmutable $at,
    ): WebsitePublicAddressData {
        if ($this->address === null
            || $this->address->tenantId !== $trustedTenantId
            || $this->address->websiteId !== $websiteId) {
            throw new InvalidWebsiteValueException(
                'Website publication requires a reserved primary public address.',
            );
        }

        return $this->address = new WebsitePublicAddressData(
            $websiteId,
            $trustedTenantId,
            $this->address->host,
            $this->address->url,
            true,
        );
    }

    public function forWebsite(string $trustedTenantId, string $websiteId): ?WebsitePublicAddressData
    {
        return $this->address?->tenantId === $trustedTenantId
            && $this->address->websiteId === $websiteId
                ? $this->address
                : null;
    }

    public function forTenant(string $trustedTenantId): ?WebsitePublicAddressData
    {
        return $this->address?->tenantId === $trustedTenantId ? $this->address : null;
    }

    public function resolveActiveHost(string $host): ?WebsitePublicAddressData
    {
        return $this->address?->active === true
            && strtolower($host) === $this->address->host
                ? $this->address
                : null;
    }
}

final class DashboardFixedBookingConfigurationRepository implements BookingFormConfigurationRepositoryInterface
{
    private BookingFormConfiguration $configuration;

    public function __construct()
    {
        $tenant = new BookingTenantId('00000000-0000-4000-8000-000000000002');
        $this->configuration = BookingFormConfiguration::create(
            $tenant,
            false,
            false,
            false,
            false,
            false,
            new RequiredFields([]),
            new FieldOrder([
                BookingFormField::PatientName,
                BookingFormField::Phone,
                BookingFormField::AppointmentDate,
                BookingFormField::AppointmentTime,
            ]),
            new FieldLabels([]),
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
        $this->configuration->synchronizeVersion(1);
    }

    public function findByTenant(BookingTenantId $tenantId): ?BookingFormConfiguration
    {
        return $tenantId->value === $this->configuration->tenantId->value
            ? $this->configuration
            : null;
    }

    public function save(BookingFormConfiguration $configuration): void
    {
        $configuration->synchronizeVersion($configuration->version() + 1);
        $this->configuration = $configuration;
    }
}

final readonly class DashboardFixedBookingServiceRepository implements ServiceRepositoryInterface
{
    /** @return list<Service> */
    private function services(): array
    {
        $tenant = new BookingTenantId('00000000-0000-4000-8000-000000000002');

        return [
            Service::register(
                new BookingServiceId('00000000-0000-4000-8000-000000000020'),
                $tenant,
                new ServiceName('Consultation'),
                null,
                new SortOrder(1),
                new DateTimeImmutable('2026-01-01T00:00:00Z'),
            ),
        ];
    }

    public function findById(BookingTenantId $tenantId, BookingServiceId $serviceId): ?Service
    {
        return $this->services()[0];
    }

    public function findAll(BookingTenantId $tenantId): array
    {
        return $this->services();
    }

    public function findActive(BookingTenantId $tenantId): array
    {
        return $this->services();
    }

    public function existsByName(BookingTenantId $tenantId, string $name): bool
    {
        return $name === 'Consultation';
    }

    public function save(Service $service): void {}
}

final class DashboardFixedClinicRepository implements ClinicRepositoryInterface
{
    private Clinic $clinic;

    public function __construct()
    {
        $this->clinic = Clinic::reconstitute(
            new ClinicId('00000000-0000-4000-8000-000000000003'),
            new TenantId('00000000-0000-4000-8000-000000000002'),
            new IanaTimezone('Asia/Kuala_Lumpur'),
            new WeeklyOperatingHours([
                1 => [new OpeningInterval(new LocalTime('09:00'), new LocalTime('17:00'))],
                2 => [new OpeningInterval(new LocalTime('09:00'), new LocalTime('17:00'))],
                6 => [new OpeningInterval(new LocalTime('09:00'), new LocalTime('13:00'))],
            ]),
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
            1,
            contactProfile: new ClinicContactProfile(
                '+60312345678',
                'clinic@aisyah.test',
                'Kuala Lumpur',
                '+60123456789',
                3.139,
                101.6869,
            ),
        );
    }

    public function findById(TenantId $tenantId, ClinicId $clinicId): ?Clinic
    {
        return $tenantId->value === $this->clinic->tenantId->value
            && $clinicId->value === $this->clinic->id->value
            ? $this->clinic
            : null;
    }

    public function findByTenantId(TenantId $tenantId): ?Clinic
    {
        return $tenantId->value === $this->clinic->tenantId->value ? $this->clinic : null;
    }

    public function save(Clinic $clinic): void
    {
        $clinic->synchronizeVersion($clinic->version() + 1);
        $this->clinic = $clinic;
    }
}

final readonly class DashboardClinicTransaction implements ClinicTransactionInterface
{
    public function run(callable $operation): mixed
    {
        return $operation();
    }
}

final readonly class DashboardWebsitePublicationTransaction implements WebsitePublicationTransactionInterface
{
    public function run(string $tenantId, string $websiteId, callable $operation): mixed
    {
        return $operation();
    }
}

final readonly class DashboardAuditRecorder implements AuditEntryRecorderInterface
{
    public function record(AuditEntryData $auditEntry): AuditEntry
    {
        return AuditEntry::record(
            new AuditEntryId($auditEntry->auditEntryId),
            $auditEntry->occurredAt,
            AuditActorType::from($auditEntry->actor->type),
            $auditEntry->actor->identityId,
            $auditEntry->tenantId,
            $auditEntry->action,
            $auditEntry->target->type,
            $auditEntry->target->id,
            AuditOutcomeType::from($auditEntry->outcome->outcome),
            $auditEntry->outcome->reasonCode,
            $auditEntry->correlationId,
            $auditEntry->safeMetadata,
        );
    }
}

final class DashboardFixedWebsiteRepository implements WebsiteRepositoryInterface
{
    private Website $website;

    public function __construct()
    {
        $uuid = static fn (int $suffix): string => sprintf('00000000-0000-4000-8000-%012d', $suffix);
        $this->website = Website::create(
            new WebsiteId($uuid(1)),
            new TenantId($uuid(2)),
            TemplateId::SyifaEssential,
            new WebsiteBranding('Klinik Aisyah', 'Trusted healthcare', '#112233', '#445566', null, null, 'hello@aisyah.test', '+60123456789', 'Kuala Lumpur'),
            array_map(static fn (int $suffix): SectionId => new SectionId($uuid($suffix)), range(100, 108)),
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
        foreach (array_slice($this->website->sections()->sections(), 1) as $section) {
            $this->website->disableSection(
                $section->id,
                new DateTimeImmutable('2026-01-01T00:01:00Z'),
            );
        }
        $this->website->synchronizeVersion(1);
    }

    public function findById(TenantId $tenantId, WebsiteId $websiteId): ?Website
    {
        return $tenantId->value === $this->website->tenantId->value
            && $websiteId->value === $this->website->id->value ? $this->website : null;
    }

    public function findByTenant(TenantId $tenantId): ?Website
    {
        return $tenantId->value === $this->website->tenantId->value ? $this->website : null;
    }

    public function save(Website $website): void
    {
        $website->synchronizeVersion($website->version() + 1);
    }

    public function lifecycle(): string
    {
        return $this->website->lifecycle()->value;
    }

    public function hasPublishedSnapshot(): bool
    {
        return $this->website->publishedSnapshot() !== null;
    }

    public function assetCount(): int
    {
        return count($this->website->assets()->assets());
    }

    public function publishedHeadline(): ?string
    {
        return $this->website->publishedSnapshot()
            ?->sectionContents[0]
            ->content instanceof HeroSectionContent
                ? $this->website->publishedSnapshot()?->sectionContents[0]->content->headline
                : null;
    }

    public function enableIncompleteAbout(): void
    {
        $this->website->enableSection(
            $this->website->sections()->sections()[1]->id,
            new DateTimeImmutable('2026-01-01T00:02:00Z'),
        );
    }
}

final class DashboardWebsiteAssetStorage implements WebsiteAssetBinaryStorageInterface
{
    /** @var array<string, string> */
    public array $files = [];

    public function store(string $storageKey, string $contents): void
    {
        $this->files[$storageKey] = $contents;
    }

    public function delete(string $storageKey): void
    {
        unset($this->files[$storageKey]);
    }
}

final class DashboardFixedWebsiteDraftRepository implements WebsiteDraftRepositoryInterface
{
    private WebsiteDraftContent $draft;

    public function __construct()
    {
        $uuid = static fn (int $suffix): string => sprintf(
            '00000000-0000-4000-8000-%012d',
            $suffix,
        );
        $section = static fn (int $suffix): SectionId => new SectionId($uuid($suffix));
        $this->draft = new WebsiteDraftContent(
            new WebsiteId($uuid(1)),
            new TenantId($uuid(2)),
            1,
            [
                new HeroSectionContent($section(100), 'Trusted healthcare'),
                new AboutSectionContent($section(101)),
                new ServicesSectionContent($section(102)),
                new DoctorsSectionContent($section(103)),
                new TestimonialsSectionContent($section(104)),
                new GallerySectionContent($section(105)),
                new FaqSectionContent($section(106)),
                new ContactSectionContent($section(107)),
                new BookingCtaSectionContent($section(108)),
            ],
        );
    }

    public function find(TenantId $tenantId, WebsiteId $websiteId): ?WebsiteDraftContent
    {
        return $tenantId->value === $this->draft->tenantId->value
            && $websiteId->value === $this->draft->websiteId->value
                ? $this->draft
                : null;
    }

    public function save(WebsiteDraftContent $draft, int $expectedVersion): WebsiteDraftContent
    {
        return $this->draft = new WebsiteDraftContent(
            $draft->websiteId,
            $draft->tenantId,
            $expectedVersion + 1,
            $draft->sections,
        );
    }

    public function replaceHero(string $headline): void
    {
        $sections = $this->draft->sections;
        $sections[0] = new HeroSectionContent($sections[0]->sectionId(), $headline);
        $this->draft = new WebsiteDraftContent(
            $this->draft->websiteId,
            $this->draft->tenantId,
            $this->draft->version + 1,
            $sections,
        );
    }
}

final readonly class DashboardFixedPublicBookingConfiguration implements PublicBookingFormConfigurationReaderInterface
{
    public function forTrustedTenant(string $trustedTenantId): PublicBookingFormConfiguration
    {
        return new PublicBookingFormConfiguration(
            false,
            false,
            false,
            false,
            [new PublicBookingServiceOption(
                '00000000-0000-4000-8000-000000000201',
                'Consultation',
                false,
            )],
        );
    }
}

final readonly class DashboardFixedWebsiteRead implements WebsiteReadInterface
{
    public function summary(string $trustedTenantId): ?WebsiteSummaryData
    {
        return new WebsiteSummaryData('website-1', $trustedTenantId, 'Klinik Aisyah', 'syifa-essential', 'published');
    }

    public function detail(string $trustedTenantId): ?WebsiteDetailData
    {
        return new WebsiteDetailData(
            'website-1', $trustedTenantId, 'syifa-essential', 'published', 'Klinik Aisyah',
            'Trusted family care', '#112233', '#445566', null, null,
            'hello@aisyah.test', '+60123456789', 'Kuala Lumpur', [],
        );
    }
}

final readonly class DashboardFixedWebsiteSnapshot implements WebsitePublishedSnapshotReadInterface
{
    public function latest(string $websiteId): ?PublishedWebsiteSnapshotData
    {
        return new PublishedWebsiteSnapshotData(
            'publication-1',
            $websiteId,
            3,
            new DateTimeImmutable('2026-08-20T10:00:00Z'),
            'syifa-essential',
            'Klinik Aisyah',
            'Klinik Aisyah',
            'hash',
            [
                new PublishedWebsiteSectionSummaryData('HERO', 1, true, true, 1, ['Trusted healthcare']),
                new PublishedWebsiteSectionSummaryData('ABOUT', 2, true, true, 1, ['About us']),
                new PublishedWebsiteSectionSummaryData('SERVICES', 3, true, true, 3, ['Primary care', 'Vaccination', 'General practice']),
                new PublishedWebsiteSectionSummaryData('DOCTORS', 4, true, true, 2, ['Dr Aisyah', 'Dr Kumar']),
                new PublishedWebsiteSectionSummaryData('TESTIMONIALS', 5, true, true, 2, ['Patient A', 'Patient B']),
                new PublishedWebsiteSectionSummaryData('GALLERY', 6, true, true, 4, ['Reception']),
                new PublishedWebsiteSectionSummaryData('FAQ', 7, true, true, 5, ['When are you open?']),
                new PublishedWebsiteSectionSummaryData('CONTACT', 8, true, true, 1, ['Kuala Lumpur', '+6012']),
            ],
        );
    }
}

final readonly class DashboardFixedSeoSummary implements WebsiteSeoSummaryReadInterface
{
    public function summary(string $websiteId): ?WebsiteSeoSummaryData
    {
        return new WebsiteSeoSummaryData('Klinik Aisyah', 'index,follow', true);
    }
}

final readonly class DashboardFixedBookingRead implements ClinicOwnerBookingReadInterface
{
    public function detail(string $trustedTenantId, string $bookingId): ?BookingDetailData
    {
        if ($bookingId !== '00000000-0000-4000-8000-000000000001') {
            return null;
        }

        return $this->booking($trustedTenantId);
    }

    public function list(string $trustedTenantId, ?string $status, ?string $cursor, int $limit, ?string $search = null, ?string $source = null): array
    {
        return [
            $this->booking($trustedTenantId),
        ];
    }

    public function countByStatus(string $trustedTenantId): array
    {
        return ['submitted' => 1];
    }

    public function countBySource(string $trustedTenantId): array
    {
        return ['WEBSITE' => 1];
    }

    public function history(string $trustedTenantId, string $bookingId): array
    {
        if ($this->detail($trustedTenantId, $bookingId) === null) {
            return [];
        }

        return [new BookingHistoryData(
            'history-1',
            'BookingSubmitted',
            'public_visitor',
            null,
            '2026-08-31T01:00:00Z',
            [
                'source' => 'WEBSITE',
                'local_date' => '2026-09-01',
                'local_start' => '09:00',
                'local_end' => '09:30',
                'timezone' => 'Asia/Kuala_Lumpur',
                'starts_at_utc' => '2026-09-01T01:00:00Z',
                'ends_at_utc' => '2026-09-01T01:30:00Z',
                'duration_minutes' => 30,
            ],
        )];
    }

    private function booking(string $trustedTenantId): BookingDetailData
    {
        return new BookingDetailData(
            '00000000-0000-4000-8000-000000000001',
            $trustedTenantId,
            'service-1',
            'Consultation',
            'BOOK-001',
            'WEBSITE',
            'submitted',
            'Patient Name',
            '+6012',
            'patient@example.test',
            'Booking notes',
            '2026-09-01',
            '09:00',
            '09:30',
            'Asia/Kuala_Lumpur',
            '2026-09-01T01:00:00Z',
            '2026-09-01T01:30:00Z',
            30,
            '2026-08-31T01:00:00Z',
        );
    }
}

final readonly class DashboardFixedBookingFormRead implements PublicBookingFormReaderInterface
{
    public function forTrustedTenant(string $trustedTenantId): PublicBookingFormReaderData
    {
        return new PublicBookingFormReaderData(
            true,
            true,
            true,
            true,
            [new PublicBookingFormServiceData('00000000-0000-4000-8000-000000000004', 'Consultation')],
        );
    }
}

final class DashboardClinicBookingDateOverrides implements ClinicBookingDateOverrideRepositoryInterface
{
    /** @var array<string, ClinicBookingDateOverrideData> */
    private array $items = [];

    public function allForClinic(ClinicId $clinicId): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (ClinicBookingDateOverrideData $item, string $key): bool => str_starts_with($key, $clinicId->value.'|'),
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    public function replace(ClinicId $clinicId, string $localDate, bool $closed, array $intervals, int $expectedVersion): ClinicBookingDateOverrideData
    {
        $key = $clinicId->value.'|'.$localDate;
        $current = $this->items[$key] ?? null;
        if (($current?->version ?? 0) !== $expectedVersion) {
            throw new StaleClinicWriteException('Booking date override changed since it was loaded.');
        }

        return $this->items[$key] = new ClinicBookingDateOverrideData($localDate, $closed, $intervals, $expectedVersion + 1);
    }

    public function delete(ClinicId $clinicId, string $localDate, int $expectedVersion): void
    {
        $key = $clinicId->value.'|'.$localDate;
        if (($this->items[$key]->version ?? 0) !== $expectedVersion) {
            throw new StaleClinicWriteException('Booking date override changed since it was loaded.');
        }
        unset($this->items[$key]);
    }
}

final class DashboardRecordedBookingOperations implements ClinicOwnerBookingOperationsInterface
{
    /** @var list<list<string>> */
    public array $calls = [];

    public function confirm(string $tenantId, string $bookingId, string $actorId, string $actorRole): void
    {
        $this->calls[] = ['confirm', $tenantId, $bookingId, $actorId, $actorRole];
    }

    public function cancel(string $tenantId, string $bookingId, string $actorId, string $actorRole): void
    {
        $this->calls[] = ['cancel', $tenantId, $bookingId, $actorId, $actorRole];
    }

    public function complete(string $tenantId, string $bookingId, string $actorId, string $actorRole): void
    {
        $this->calls[] = ['complete', $tenantId, $bookingId, $actorId, $actorRole];
    }

    public function reschedule(string $tenantId, string $bookingId, string $localDate, string $localStart, string $actorId, string $actorRole): void
    {
        $this->calls[] = ['reschedule', $tenantId, $bookingId, $localDate, $localStart, $actorId, $actorRole];
    }
}

final readonly class DashboardFixedReportRead implements ReportReadInterface
{
    public function clinic(string $tenantId): array
    {
        return $this->report('tenant', $tenantId);
    }

    public function designer(string $platformIdentityId): array
    {
        return $this->report('designer_assignment', $platformIdentityId);
    }

    public function portfolio(): array
    {
        return $this->report('platform_portfolio', null);
    }

    /** @return array<string, mixed> */
    private function report(string $scope, ?string $scopeId): array
    {
        return [
            'scope' => $scope,
            'scopeId' => $scopeId,
            'freshAt' => '2026-07-28T10:00:00+00:00',
            'definitions' => [[
                'key' => 'booking.total',
                'label' => 'Total bookings',
                'version' => 1,
                'freshness' => 'live',
            ]],
            'metrics' => ['bookingTotal' => 1],
        ];
    }
}

final readonly class DashboardFixedNotificationRead implements NotificationReadInterface
{
    public function forTenant(string $tenantId, ?string $status, ?string $triggerType): array
    {
        return $this->entries($tenantId);
    }

    public function forPlatform(?string $tenantId, ?string $status, ?string $triggerType): array
    {
        return $this->entries($tenantId);
    }

    /** @return array{entries: list<array<string, mixed>>} */
    private function entries(?string $tenantId): array
    {
        return ['entries' => [[
            'id' => '00000000-0000-4000-8000-000000000901',
            'tenantId' => $tenantId,
            'category' => 'booking_received',
            'triggerType' => 'booking',
            'triggerId' => 'booking-1',
            'recipientReference' => 'clinic_owner',
            'status' => 'queued',
            'createdAt' => '2026-07-28T10:00:00+00:00',
            'updatedAt' => '2026-07-28T10:00:00+00:00',
            'attempts' => [],
        ]]];
    }
}

final readonly class DashboardFixedAuditRead implements AuditEntryReadInterface
{
    public function search(
        ?string $action,
        ?string $outcome,
        ?string $actorType,
        ?string $tenantId,
        ?string $correlationId,
    ): array {
        return [
            'entries' => [[
                'id' => '00000000-0000-4000-8000-000000000801',
                'occurredAt' => '2026-07-28T10:00:00+00:00',
                'actorType' => 'system',
                'actorIdentityId' => null,
                'tenantId' => null,
                'action' => 'website.published',
                'targetType' => 'website',
                'targetId' => '00000000-0000-4000-8000-000000000001',
                'outcome' => 'succeeded',
                'reasonCode' => null,
                'correlationId' => '00000000-0000-4000-8000-000000000802',
            ]],
        ];
    }
}

final readonly class DashboardFixedCommercialCatalogue implements BillingOptionCatalogueQueryInterface, CapabilityDefinitionCatalogueQueryInterface, CommercialCatalogueQueryInterface, PlanCatalogueQueryInterface, PlanOfferingCatalogueQueryInterface, PricingHistoryReadInterface
{
    private const string PLAN_ID = '10000000-0000-4000-8000-000000000001';

    private const string BILLING_OPTION_ID = '10000000-0000-4000-8000-000000000002';

    private const string OFFERING_ID = '10000000-0000-4000-8000-000000000003';

    private const string CAPABILITY_ID = '10000000-0000-4000-8000-000000000004';

    public function findPlan(string $planId): ?PlanData
    {
        return $planId === self::PLAN_ID ? $this->plan() : null;
    }

    public function findBillingOption(string $billingOptionId): ?BillingOptionData
    {
        return $billingOptionId === self::BILLING_OPTION_ID ? $this->billingOption() : null;
    }

    public function findPlanOffering(string $planOfferingId): ?PlanOfferingData
    {
        return $planOfferingId === self::OFFERING_ID ? $this->offering() : null;
    }

    public function findCapability(string $capabilityId): ?CapabilityDefinitionData
    {
        return $capabilityId === self::CAPABILITY_ID ? $this->capability() : null;
    }

    public function listPlans(OffsetPaginationInput $pagination): PaginatedPlanData
    {
        return new PaginatedPlanData([$this->plan()], $this->meta($pagination));
    }

    public function listBillingOptions(OffsetPaginationInput $pagination): PaginatedBillingOptionData
    {
        return new PaginatedBillingOptionData([$this->billingOption()], $this->meta($pagination));
    }

    public function listCapabilityDefinitions(OffsetPaginationInput $pagination): PaginatedCapabilityDefinitionData
    {
        return new PaginatedCapabilityDefinitionData([$this->capability()], $this->meta($pagination));
    }

    public function listPlanOfferings(OffsetPaginationInput $pagination): PaginatedPlanOfferingData
    {
        return new PaginatedPlanOfferingData([$this->offering()], $this->meta($pagination));
    }

    public function forPlanOffering(string $planOfferingId): array
    {
        return $planOfferingId === self::OFFERING_ID
            ? [new PricingHistoryData(2, 120000, 'MYR', '2026-01-01', null, 'essential-v2', '2026-07-01T00:00:00Z')]
            : [];
    }

    private function plan(): PlanData
    {
        return new PlanData(
            self::PLAN_ID,
            'syifa-essential',
            'Syifa Essential',
            'Annual clinic website plan.',
            'active',
            1,
            '2026-01-01T00:00:00Z',
            '2026-07-01T00:00:00Z',
        );
    }

    private function billingOption(): BillingOptionData
    {
        return new BillingOptionData(
            self::BILLING_OPTION_ID,
            'annual',
            'Annual',
            'available',
            'recurring',
            'year',
            1,
            '2026-01-01',
            null,
            1,
        );
    }

    private function capability(): CapabilityDefinitionData
    {
        return new CapabilityDefinitionData(
            self::CAPABILITY_ID,
            'booking.manage',
            'Booking management',
            'Manage clinic bookings.',
            'Included operational capability.',
            'active',
        );
    }

    private function offering(): PlanOfferingData
    {
        return new PlanOfferingData(
            self::OFFERING_ID,
            self::PLAN_ID,
            self::BILLING_OPTION_ID,
            120000,
            'MYR',
            'active',
            '2026-01-01',
            null,
            '2',
            'essential-v2',
            1,
        );
    }

    private function meta(OffsetPaginationInput $pagination): OffsetPaginationMeta
    {
        return new OffsetPaginationMeta(1, $pagination->perPage, 1, 1, 1, 1);
    }
}
