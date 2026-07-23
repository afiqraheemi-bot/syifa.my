<?php

declare(strict_types=1);

namespace Tests\Feature\Support\Dashboard;

use App\Modules\Booking\Contracts\Operations\ClinicOwnerBookingOperationsInterface;
use App\Modules\Booking\Contracts\Queries\BookingDetailData;
use App\Modules\Booking\Contracts\Queries\ClinicOwnerBookingReadInterface;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerDashboardData;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerDashboardReadInterface;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerJobDetailData;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerQueueJobData;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerRecentAssignmentData;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionSummaryData;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionSummaryReadInterface;
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
use App\Support\Authorization\Application\AuthorizationService;
use App\Support\Identity\ActorType;
use App\Support\Identity\AuthenticatedIdentity;
use App\Support\Identity\AuthenticatedIdentityInterface;
use App\Support\Identity\CurrentUserInterface;
use App\Support\Identity\PermissionResolverInterface;
use App\Support\Identity\RoleResolverInterface;
use DateTimeImmutable;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class AuthenticatedDashboardIntegrationTest extends TestCase
{
    private DashboardRecordedBookingOperations $bookingOperations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(ClinicSummaryReadInterface::class, new DashboardFixedClinicSummary);
        $this->app->instance(SubscriptionSummaryReadInterface::class, new DashboardFixedSubscriptionSummary);
        $this->app->instance(WebsiteReadInterface::class, new DashboardFixedWebsiteRead);
        $this->app->instance(WebsitePublishedSnapshotReadInterface::class, new DashboardFixedWebsiteSnapshot);
        $this->app->instance(WebsiteSeoSummaryReadInterface::class, new DashboardFixedSeoSummary);
        $this->app->instance(ClinicOwnerBookingReadInterface::class, new DashboardFixedBookingRead);
        $this->app->instance(WebsiteDesignerDashboardReadInterface::class, new DashboardFixedDesignerRead);
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
                    ->has('navigation', 4)
                    ->where('welcomeTitle', 'Welcome back, Authenticated User')
                    ->has('summaries', 3)
                    ->where('summaries.0.key', 'clinic')
                    ->where('summaries.0.value', 'Asia/Kuala_Lumpur')
                    ->where('summaries.1.key', 'subscription')
                    ->where('summaries.1.value', 'Active')
                    ->where('summaries.2.key', 'bookings')
                    ->has('quickActions', 3)
                    ->where('quickActions.0.available', false)
                    ->where('recentActivity', []),
            );
        } elseif ($role === 'website_designer') {
            $response->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->has('navigation', 2)
                    ->where('navigation.1.key', 'onboarding'),
            );
        } else {
            $response->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page->has('navigation', 1),
            );
        }
    }

    public function test_website_designer_receives_the_dashboard_overview_from_onboarding_query_projections(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::PlatformIdentity, 'website_designer'),
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
                    ->where('quickActions.0.available', false)
                    ->has('recentAssignments', 1)
                    ->where('recentAssignments.0.description', 'Website setup')
                    ->has('navigation', 2),
            );
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

    public function test_website_designer_receives_assignment_scoped_operational_job_detail(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::PlatformIdentity, 'website_designer'),
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
                    ->where('job.actions.1.available', false),
            );
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
    }

    public function test_clinic_owner_receives_the_website_overview_from_application_providers(): void
    {
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
                    ->where('domainStatus.value', 'Not available')
                    ->where('themeInformation.value', 'syifa-essential')
                    ->where('seoStatus.value', 'Indexing enabled')
                    ->has('quickActions', 3)
                    ->has('navigation', 4),
            );
    }

    public function test_clinic_owner_receives_the_website_content_overview_from_published_query_evidence(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::ClinicOwner, 'clinic_owner', 'tenant-1'),
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
                    ->has('navigation', 4),
            );
    }

    public function test_clinic_owner_navigation_remains_consistent_across_every_portal_page(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::ClinicOwner, 'clinic_owner', 'tenant-1'),
        );

        foreach (['/dashboard', '/dashboard/website', '/dashboard/website/content', '/dashboard/bookings'] as $uri) {
            $this->get($uri)
                ->assertOk()
                ->assertInertia(
                    static fn (AssertableInertia $page): AssertableInertia => $page
                        ->where('navigation.0.key', 'dashboard')
                        ->where('navigation.1.key', 'website')
                        ->where('navigation.2.key', 'content')
                        ->where('navigation.3.key', 'bookings'),
                );
        }
    }

    public function test_clinic_owner_receives_the_booking_overview_from_application_providers(): void
    {
        $this->app->instance(
            AuthorizationService::class,
            $this->authorization(ActorType::ClinicOwner, 'clinic_owner', 'tenant-1'),
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
                    ->has('navigation', 4),
            );
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
            'Shared/Dashboard/AuthenticatedDashboard',
        ];
    }

    private function authorization(
        ?ActorType $actorType = null,
        ?string $role = null,
        ?string $tenantId = null,
    ): AuthorizationService {
        $identity = $actorType === null || $role === null
            ? null
            : new AuthenticatedIdentity(
                $actorType,
                'identity-1',
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

final readonly class DashboardFixedClinicSummary implements ClinicSummaryReadInterface
{
    public function summary(string $trustedTenantId): ?ClinicSummaryData
    {
        return new ClinicSummaryData('clinic-1', 'Asia/Kuala_Lumpur', true);
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
        if ($onboardingJobId !== '00000000-0000-4000-8000-000000000101') {
            return null;
        }

        return new WebsiteDesignerJobDetailData(
            'assignment-1',
            $onboardingJobId,
            'tenant-1',
            'website-1',
            'in_progress',
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
        return null;
    }

    public function list(string $trustedTenantId, ?string $status, ?string $cursor, int $limit, ?string $search = null, ?string $source = null): array
    {
        return [
            new BookingDetailData(
                'booking-1', $trustedTenantId, 'service-1', 'BOOK-001', 'WEBSITE', 'submitted',
                '2026-09-01', '09:00', '09:30', 'Asia/Kuala_Lumpur',
                '2026-09-01T01:00:00Z', '2026-09-01T01:30:00Z', 30,
            ),
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
        return [];
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

    public function reschedule(string $tenantId, string $bookingId, string $localDate, string $localStart, string $actorId, string $actorRole): void
    {
        $this->calls[] = ['reschedule', $tenantId, $bookingId, $localDate, $localStart, $actorId, $actorRole];
    }
}
