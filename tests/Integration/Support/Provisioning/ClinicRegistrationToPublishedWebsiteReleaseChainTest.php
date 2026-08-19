<?php

declare(strict_types=1);

namespace Tests\Integration\Support\Provisioning;

use App\Modules\ClinicRegistration\Application\DecideClinicRegistrationService;
use App\Modules\ClinicRegistration\Application\StartClinicRegistrationReviewService;
use App\Modules\ClinicRegistration\Application\StartClinicRegistrationService;
use App\Modules\ClinicRegistration\Application\SubmitClinicRegistrationService;
use App\Modules\ClinicRegistration\Application\UpdateClinicRegistrationDraftService;
use App\Modules\ClinicRegistration\Contracts\Commands\DecideClinicRegistrationCommand;
use App\Modules\ClinicRegistration\Contracts\Commands\StartClinicRegistrationCommand;
use App\Modules\ClinicRegistration\Contracts\Commands\StartClinicRegistrationReviewCommand;
use App\Modules\ClinicRegistration\Contracts\Commands\SubmitClinicRegistrationCommand;
use App\Modules\ClinicRegistration\Contracts\Commands\UpdateClinicRegistrationDraftCommand;
use App\Modules\ClinicRegistration\Contracts\Data\DeclarationAcceptanceData;
use App\Modules\Onboarding\Application\Administration\AssignWebsiteDesignerService;
use App\Modules\Onboarding\Application\Tasks\ProgressOnboardingTaskService;
use App\Modules\Onboarding\Application\WebsiteApproval\DecideWebsiteApprovalService;
use App\Modules\Onboarding\Contracts\Administration\AssignWebsiteDesignerCommand;
use App\Modules\Onboarding\Contracts\Tasks\ProgressOnboardingTaskCommand;
use App\Modules\Onboarding\Contracts\WebsiteApproval\DecideWebsiteApprovalCommand;
use App\Modules\TenantManagement\Application\Administration\EstablishClinicOwnerService;
use App\Modules\TenantManagement\Contracts\Administration\EstablishClinicOwnerCommand;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContextFactoryInterface;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicWebsiteRenderModelProviderInterface;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Modules\WebsiteBuilder\Application\WebsiteContent\ManageWebsiteContentService;
use App\Modules\WebsiteBuilder\Application\WebsiteContent\UpdateWebsiteContentCommand;
use App\Modules\WebsiteBuilder\Application\WebsiteDraft\LoadDraftWebsiteContent;
use App\Modules\WebsiteBuilder\Application\WebsiteDraft\ManageWebsiteDraftContentService;
use App\Modules\WebsiteBuilder\Application\WebsiteDraft\SaveDraftWebsiteContent;
use App\Modules\WebsiteBuilder\Application\WebsitePublication\PublishWebsiteCommand;
use App\Modules\WebsiteBuilder\Application\WebsitePublication\PublishWebsiteService;
use App\Support\Dashboard\Application\WebsiteDesigner\Job\SubmitWebsiteForReviewApplication;
use App\Support\Provisioning\Application\ActivateApprovedFreeTrialService;
use Database\Seeders\SyifaSubscriptionPackageSeeder;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Production went live and immediately hit a cascade of Postgres
 * column-name typos, entitlement bugs, and provisioning bugs that were
 * fixed reactively through ~20 unreviewed hotfixes. The root gap: the
 * default Feature-test connection is SQLite in-memory, so the real
 * Postgres migration chain — the exact thing that broke — was never
 * exercised end to end by an automated test. Individual
 * `*_POSTGRES_TEST_DSN`-gated Integration tests each hand-build only the
 * few tables they need via inline `Schema::create`, not the real,
 * fully-migrated schema in true migration order.
 *
 * This test closes that gap. It runs `migrate:fresh` against a real,
 * disposable Postgres database (`RELEASE_POSTGRES_TEST_DSN`), seeds the
 * real commercial catalogue, and then drives the entire release path
 * through the real, container-bound Application/Infrastructure services —
 * never fakes or mocks of the domain/infrastructure under test — exactly
 * as the real controllers do:
 *
 *   clinic registration submitted -> Super Admin approves -> automatic
 *   provisioning (Tenant, Website, Booking, subdomain, Onboarding Job) ->
 *   Super Admin assigns a Website Designer and establishes the Clinic
 *   Owner -> Website Designer configures the site and completes every
 *   mandatory onboarding task -> Clinic Owner approves the draft ->
 *   Website Designer publishes -> the published Website's public address
 *   is verifiably live.
 *
 * The only test doubles used are `WebsiteAuthorizationContext` values
 * (who is acting), never the domain/registration/provisioning/onboarding/
 * website services themselves.
 *
 * "Verifiably live" is checked two ways: (1) a direct row-level assertion
 * that `website_public_hosts.activated_at` is set with no
 * `inactivated_at` and `websites.lifecycle = 'published'`, and (2) by
 * resolving the host through the real, Postgres-bound
 * `PublicSiteContextFactoryInterface` and `PublicWebsiteRenderModelProviderInterface`
 * — the exact same Infrastructure adapters `PublicWebsiteController`
 * delegates to — and asserting a live render model comes back. A full
 * HTTP round trip through the Laravel test client was deliberately not
 * used: it would require binding the switched Postgres test connection
 * into host/subdomain routing middleware that has nothing to do with the
 * behavior under test, whereas resolving through the real delivery
 * services already exercises the identical production code path the
 * controller calls into.
 */
final class ClinicRegistrationToPublishedWebsiteReleaseChainTest extends TestCase
{
    private const string CONNECTION = 'release_chain_integration';

    private ?ConnectionInterface $connection = null;

    protected function setUp(): void
    {
        parent::setUp();
        $dsn = getenv('RELEASE_POSTGRES_TEST_DSN');
        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Requires RELEASE_POSTGRES_TEST_DSN pointing at a disposable PostgreSQL database.');
        }

        config()->set('database.default', self::CONNECTION);
        config()->set('database.connections.'.self::CONNECTION, [
            'driver' => 'pgsql',
            'url' => $dsn,
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
            'timezone' => 'UTC',
        ]);
        // APP_ENV=testing disables runtime host addressing by default as a
        // safety net (config/public_website_delivery.php). This test's whole
        // point is to exercise that runtime addressing path for real, against
        // the disposable Postgres database it just migrated, so it is turned
        // back on here deliberately.
        config()->set('public_website_delivery.runtime_addressing', true);
        DB::purge(self::CONNECTION);

        Artisan::call('migrate:fresh', ['--force' => true, '--database' => self::CONNECTION]);

        $this->connection = DB::connection(self::CONNECTION);
        $this->app->make(SyifaSubscriptionPackageSeeder::class)->run();
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            DB::purge(self::CONNECTION);
        }
        parent::tearDown();
    }

    public function test_a_clinic_registration_is_provisioned_onboarded_and_published_end_to_end_on_real_postgres(): void
    {
        $now = new DateTimeImmutable;
        $correlationId = (string) Str::uuid();
        $applicantId = (string) Str::uuid();
        $superAdminId = (string) Str::uuid();
        $designerId = (string) Str::uuid();
        $clinicName = 'Klinik Rantai Penuh';
        $clinicEmail = 'owner@klinikrantaipenuh.test';
        $clinicPhone = '+60123456789';
        $clinicAddress = 'No. 1, Jalan Uji Rantai, 50000 Kuala Lumpur';

        $offering = $this->connection->table('commercial_catalogue_plan_offerings')
            ->where('capability_configuration_reference', 'package:syifa-trial')
            ->where('status', 'active')
            ->where('amount_minor', 0)
            ->first(['id', 'billing_option_id', 'configuration_version']);
        self::assertNotNull($offering, 'The seeded Syifa free-trial plan offering was not found.');

        // --- Clinic registration is drafted and submitted ---------------
        $registration = $this->app->make(StartClinicRegistrationService::class)->execute(
            new StartClinicRegistrationCommand($applicantId, $now, $correlationId),
        );

        $registration = $this->app->make(UpdateClinicRegistrationDraftService::class)->execute(
            new UpdateClinicRegistrationDraftCommand(
                platformIdentityId: $applicantId,
                clinicName: $clinicName,
                clinicEmail: $clinicEmail,
                clinicPhone: $clinicPhone,
                clinicAddress: $clinicAddress,
                selectedPlanOfferingReference: (string) $offering->id,
                selectedBillingOptionReference: (string) $offering->billing_option_id,
                commercialSnapshotVersion: (string) $offering->configuration_version,
                expectedVersion: $registration->version,
                occurredAt: $now,
                correlationId: $correlationId,
                declarations: [new DeclarationAcceptanceData('terms_of_service', 'v1', $now->format(DATE_ATOM))],
                preferredSubdomain: 'klinik-rantai-penuh',
                selectedWebsiteTemplate: 'SYIFA_ESSENTIAL',
            ),
        );

        $registration = $this->app->make(SubmitClinicRegistrationService::class)->execute(
            new SubmitClinicRegistrationCommand($applicantId, $registration->version, $now, $correlationId),
        );
        $registrationId = $registration->id;
        $tenantId = $registration->reservedTenantId;
        self::assertNotNull($tenantId);

        // --- Super Admin reviews and approves ----------------------------
        $reviewVersion = $this->app->make(StartClinicRegistrationReviewService::class)->execute(
            new StartClinicRegistrationReviewCommand($registrationId, $registration->version, $superAdminId, $correlationId, $now),
        );
        $this->app->make(DecideClinicRegistrationService::class)->execute(
            new DecideClinicRegistrationCommand(
                $registrationId,
                (string) Str::uuid(),
                'approved',
                'complete_application',
                null,
                $reviewVersion,
                $superAdminId,
                $correlationId,
                $now,
            ),
        );

        $approvedStatus = $this->connection->table('clinic_registrations')->where('id', $registrationId)->value('status');
        self::assertSame('approved', $approvedStatus);

        // --- Automatic provisioning: Tenant, Website, Booking, subdomain,
        // Onboarding Job. `ActivateApprovedFreeTrialService` creates the demo
        // payment/subscription and internally drains the full 6-step
        // `ProcessProvisioningWorkflowService` workflow itself. -----------
        $activated = $this->app->make(ActivateApprovedFreeTrialService::class)->execute($registrationId, $correlationId);
        self::assertTrue($activated, 'Free trial activation did not run — the seeded offering did not satisfy the trial preconditions.');

        $tenantRow = $this->connection->table('tenants')->where('id', $tenantId)->first();
        self::assertNotNull($tenantRow, 'Provisioning did not create a real tenants row.');

        $websiteRow = $this->connection->table('websites')->where('tenant_id', $tenantId)->first();
        self::assertNotNull($websiteRow, 'Provisioning did not create a real websites row.');
        self::assertSame($clinicName, $websiteRow->clinic_name);
        self::assertSame('SYIFA_ESSENTIAL', $websiteRow->template_id);
        $websiteId = (string) $websiteRow->id;

        $bookingFormRow = $this->connection->table('booking_form_configurations')->where('tenant_id', $tenantId)->first();
        self::assertNotNull($bookingFormRow, 'Provisioning did not create the Booking capability.');

        $addressRow = $this->connection->table('website_public_hosts')->where('website_id', $websiteId)->where('is_primary', true)->first();
        self::assertNotNull($addressRow, 'Provisioning did not reserve a public Website address.');
        self::assertNull($addressRow->activated_at, 'The public address must not be active before publication.');
        $host = (string) $addressRow->normalized_host;

        $jobRow = $this->connection->table('onboarding_jobs')->where('tenant_id', $tenantId)->first();
        self::assertNotNull($jobRow, 'Provisioning did not create a real Onboarding Job row.');
        $jobId = (string) $jobRow->id;
        self::assertSame(6, $this->connection->table('onboarding_tasks')->where('onboarding_job_id', $jobId)->count());

        $registrationStatus = $this->connection->table('clinic_registrations')->where('id', $registrationId)->value('status');
        self::assertSame('provisioned', $registrationStatus);

        // --- Super Admin assigns a Website Designer and establishes the
        // Clinic Owner ----------------------------------------------------
        $now2 = new DateTimeImmutable;
        $this->connection->table('platform_workforce_credentials')->insert([
            'platform_identity_id' => $designerId,
            'normalized_email' => 'designer-'.$designerId.'@example.test',
            'password_hash' => Hash::make('correct-horse-battery-staple'),
            'email_verification_status' => 'verified',
            'email_verified_at' => $now2,
            'account_status' => 'active',
            'failed_attempt_count' => 0,
            'lockout_until' => null,
            'name' => 'Aisyah Website Designer',
            'role' => 'website_designer',
            'version' => 1,
            'created_at' => $now2,
            'updated_at' => $now2,
        ]);

        $this->app->make(AssignWebsiteDesignerService::class)->execute(
            new AssignWebsiteDesignerCommand($jobId, $designerId, $this->jobVersion($jobId), $superAdminId, $correlationId, $now2),
        );

        $ownerData = $this->app->make(EstablishClinicOwnerService::class)->executeForSelfRegistration(
            new EstablishClinicOwnerCommand($tenantId, 'Dr Aisyah Rahman', $clinicEmail, $superAdminId, $correlationId, $now2),
        );
        $clinicOwnerIdentityId = $ownerData->identityId;

        // --- Website Designer configures the site: only the Hero section
        // is left enabled (matching the established minimal-content pattern
        // used by ReadyForReviewServiceTest), branding/contact carries the
        // real clinic details, and the Hero headline is filled in via the
        // real Website Draft content service. --------------------------
        $designerContext = new WebsiteAuthorizationContext($designerId, 'website_designer', assignedTenantId: $tenantId);

        $contentResult = $this->app->make(ManageWebsiteContentService::class)->update(new UpdateWebsiteContentCommand(
            authorization: $designerContext,
            tenantId: $tenantId,
            expectedVersion: $this->websiteVersion($websiteId),
            clinicName: $clinicName,
            tagline: null,
            primaryColor: '#0F5A46',
            secondaryColor: '#1B7A5D',
            contactEmail: $clinicEmail,
            contactPhone: $clinicPhone,
            address: $clinicAddress,
            socialLinks: [],
            metaTitle: $clinicName.' | Official Website',
            metaDescription: 'Book appointments and learn about our clinic services online.',
            metaKeywords: null,
            canonicalUrl: null,
            robotsDirective: 'index,follow',
            openGraphTitle: $clinicName,
            openGraphDescription: 'Book appointments online with '.$clinicName.'.',
            indexingEnabled: true,
            sections: [
                'hero' => true,
                'about' => false,
                'services' => false,
                'doctors' => false,
                'testimonials' => false,
                'gallery' => false,
                'faq' => false,
                'contact' => false,
                'booking_cta' => false,
            ],
        ));
        self::assertSame(2, $contentResult->toArray()['version']);

        $draftService = $this->app->make(ManageWebsiteDraftContentService::class);
        $editableDraft = $draftService->load(new LoadDraftWebsiteContent($designerContext, $tenantId, $websiteId));
        $draftArray = $editableDraft->toArray();
        $sections = $draftArray['sections'];
        foreach ($sections as $index => $section) {
            if ($section['type'] === 'HERO') {
                $sections[$index]['headline'] = 'Trusted healthcare for the whole family';
            }
        }
        $draftService->save(new SaveDraftWebsiteContent($designerContext, $tenantId, $websiteId, (int) $draftArray['version'], $sections));

        // --- Website Designer completes the Website Designer mandatory
        // tasks that are ready before review: service_setup, website_setup,
        // booking_setup. `clinic_inputs` was already auto-completed by
        // provisioning. `website_approval` and `launch_readiness` cannot be
        // completed yet — the task dependency chain only makes them Ready
        // once, respectively, booking_setup and the Clinic Owner's approval
        // are done. -------------------------------------------------------
        foreach (['service_setup', 'website_setup', 'booking_setup'] as $taskKey) {
            $this->completeDesignerTask($jobId, $taskKey, $designerId, $correlationId);
        }

        // --- Website Designer requests review, which also transitions the
        // Website to ready_for_review and the Onboarding Job to in_review.
        $submitForReview = $this->app->make(SubmitWebsiteForReviewApplication::class);
        $submitForReview->execute(
            $designerContext,
            $tenantId,
            $websiteId,
            $jobId,
            (string) Str::uuid(),
            $this->websiteVersion($websiteId),
            $this->draftVersion($websiteId),
            $this->jobVersion($jobId),
            $correlationId,
            new DateTimeImmutable,
        );
        self::assertSame('in_review', $this->connection->table('onboarding_jobs')->where('id', $jobId)->value('status'));

        // --- Clinic Owner approves the draft ------------------------------
        $this->app->make(DecideWebsiteApprovalService::class)->execute(
            new DecideWebsiteApprovalCommand(
                $tenantId,
                $jobId,
                $clinicOwnerIdentityId,
                'approve',
                null,
                $this->jobVersion($jobId),
                $correlationId,
                new DateTimeImmutable,
            ),
        );
        self::assertSame('completed', $this->taskStatus($jobId, 'website_approval'));
        self::assertSame('in_review', $this->connection->table('onboarding_jobs')->where('id', $jobId)->value('status'));

        // --- Website Designer completes the final mandatory task, which
        // moves the Onboarding Job to ready_for_launch automatically. -----
        $this->completeDesignerTask($jobId, 'launch_readiness', $designerId, $correlationId);
        self::assertSame('ready_for_launch', $this->connection->table('onboarding_jobs')->where('id', $jobId)->value('status'));

        // --- Website Designer publishes ------------------------------------
        $publicationResult = $this->app->make(PublishWebsiteService::class)->handle(new PublishWebsiteCommand(
            $designerContext,
            $tenantId,
            $websiteId,
            (string) Str::uuid(),
            $this->websiteVersion($websiteId),
            $this->draftVersion($websiteId),
        ));
        self::assertSame('published', $publicationResult->lifecycle);

        // --- The published Website's public address is verifiably live ---
        $addressRow = $this->connection->table('website_public_hosts')->where('website_id', $websiteId)->where('is_primary', true)->first();
        self::assertNotNull($addressRow->activated_at, 'The public address was not activated on publish.');
        self::assertNull($addressRow->inactivated_at);
        self::assertSame('published', $this->connection->table('websites')->where('id', $websiteId)->value('lifecycle'));

        $siteContext = $this->app->make(PublicSiteContextFactoryInterface::class)->forHost($host);
        self::assertNotNull($siteContext, 'The real Postgres-backed public site resolver could not resolve the published host.');
        self::assertSame($websiteId, $siteContext->websiteId);

        $renderModel = $this->app->make(PublicWebsiteRenderModelProviderInterface::class)->find($siteContext);
        self::assertNotNull($renderModel, 'The real public Website render provider reports the published site is not live.');
        self::assertSame($clinicName, $renderModel->footer->clinicName);
    }

    private function completeDesignerTask(string $jobId, string $taskKey, string $designerId, string $correlationId): void
    {
        $taskId = $this->connection->table('onboarding_tasks')
            ->where('onboarding_job_id', $jobId)
            ->where('task_key', $taskKey)
            ->value('id');
        if (! is_string($taskId)) {
            throw new RuntimeException('Onboarding task '.$taskKey.' was not provisioned.');
        }

        $this->app->make(ProgressOnboardingTaskService::class)->execute(new ProgressOnboardingTaskCommand(
            $jobId,
            $taskId,
            'complete',
            $this->jobVersion($jobId),
            $designerId,
            'website_designer',
            null,
            'evidence:'.$taskKey,
            null,
            null,
            $correlationId,
            new DateTimeImmutable,
        ));
    }

    private function taskStatus(string $jobId, string $taskKey): string
    {
        return (string) $this->connection->table('onboarding_tasks')
            ->where('onboarding_job_id', $jobId)
            ->where('task_key', $taskKey)
            ->value('status');
    }

    private function jobVersion(string $jobId): int
    {
        return (int) $this->connection->table('onboarding_jobs')->where('id', $jobId)->value('version');
    }

    private function websiteVersion(string $websiteId): int
    {
        return (int) $this->connection->table('websites')->where('id', $websiteId)->value('version');
    }

    private function draftVersion(string $websiteId): int
    {
        return (int) $this->connection->table('website_drafts')->where('website_id', $websiteId)->value('version');
    }
}
