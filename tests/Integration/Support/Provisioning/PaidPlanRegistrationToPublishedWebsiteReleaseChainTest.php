<?php

declare(strict_types=1);

namespace Tests\Integration\Support\Provisioning;

use App\Modules\ClinicRegistration\Application\DecideClinicRegistrationService;
use App\Modules\ClinicRegistration\Application\StartClinicRegistrationReviewService;
use App\Modules\ClinicRegistration\Application\StartClinicRegistrationService;
use App\Modules\ClinicRegistration\Application\SubmitClinicRegistrationService;
use App\Modules\ClinicRegistration\Application\UpdateClinicRegistrationDraftService;
use App\Modules\ClinicRegistration\Contracts\Checkout\StartPublicInitialAcquisitionCheckoutCommand;
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
use App\Modules\SubscriptionBilling\Application\Payment\ApplyAuthoritativePaymentVerificationService;
use App\Modules\SubscriptionBilling\Application\Payment\ReceivePaymentProviderWebhookService;
use App\Modules\SubscriptionBilling\Application\Payment\StartPublicInitialAcquisitionCheckoutService;
use App\Modules\SubscriptionBilling\Application\Payment\VerifyProviderWebhookReceiptService;
use App\Modules\SubscriptionBilling\Application\Subscription\ActivateSubscriptionFromVerifiedPaymentService;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookRequest;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\PublishPaymentOutboxService;
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
use App\Support\Provisioning\Application\ProcessProvisioningWorkflowService;
use App\Support\Provisioning\Infrastructure\PublishSubscriptionProvisioningOutboxService;
use Database\Seeders\SyifaSubscriptionPackageSeeder;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Increment 5 closed the gap where paid (Syifa Basic/Pro) subscription
 * activation was never wired to a real payment event in production — see
 * ADR-011 and docs/36_SUBSCRIPTION_ACTIVATION_IMPLEMENTATION_PLAN.md. This
 * test proves the whole paid vertical slice end to end against real
 * Postgres, following the same no-fakes-of-the-domain discipline as
 * ClinicRegistrationToPublishedWebsiteReleaseChainTest.php:
 *
 *   clinic registration submitted -> Super Admin approves -> a paid Syifa
 *   Basic checkout session is started -> a signed ToyyibPay webhook reports
 *   the payment succeeded -> the payment is authoritatively verified ->
 *   HandleVerifiedPaymentSucceededForSubscriptionActivation (the new
 *   listener) picks up the real VerifiedPaymentSucceeded outbox event and
 *   registers a Subscription activation -> the Subscription is activated ->
 *   the same provisioning outbox/workflow machinery the free-trial path
 *   uses provisions the tenant -> the Website is designed, approved and
 *   published -> its public address is verifiably live.
 *
 * Only the external ToyyibPay HTTP calls are faked (via Http::fake); every
 * other service, repository, and the event listener itself are the real,
 * container-bound production wiring.
 */
final class PaidPlanRegistrationToPublishedWebsiteReleaseChainTest extends TestCase
{
    private const string CONNECTION = 'paid_plan_release_chain_integration';

    private const string TOYYIBPAY_BASE_URL = 'https://toyyibpay.test';

    private const string TOYYIBPAY_SECRET_KEY = 'test-secret-key';

    private const string TOYYIBPAY_BILL_CODE = 'bc-test-1';

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
        config()->set('public_website_delivery.runtime_addressing', true);
        config()->set('payment_providers.toyyibpay.secret_key', self::TOYYIBPAY_SECRET_KEY);
        config()->set('payment_providers.toyyibpay.category_code', 'test-category');
        config()->set('payment_providers.toyyibpay.return_url', 'https://syifa.test/payments/return');
        config()->set('payment_providers.toyyibpay.callback_url', 'https://syifa.test/api/v1/payment-provider-webhooks/toyyibpay');
        config()->set('payment_providers.toyyibpay.base_url', self::TOYYIBPAY_BASE_URL);
        DB::purge(self::CONNECTION);

        Artisan::call('migrate:fresh', ['--force' => true, '--database' => self::CONNECTION]);

        $this->connection = DB::connection(self::CONNECTION);
        $this->app->make(SyifaSubscriptionPackageSeeder::class)->run();
        $this->connection->table('payment_provider_configurations')->where('provider_key', 'toyyibpay')->update([
            'enabled' => true, 'verification_passed' => true, 'webhook_configured' => true,
            'provider_ready' => true, 'is_default' => true,
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            DB::purge(self::CONNECTION);
        }
        parent::tearDown();
    }

    public function test_a_paid_syifa_basic_registration_is_activated_provisioned_and_published_end_to_end_on_real_postgres(): void
    {
        $now = new DateTimeImmutable;
        $correlationId = (string) Str::uuid();
        $applicantId = (string) Str::uuid();
        $superAdminId = (string) Str::uuid();
        $designerId = (string) Str::uuid();
        $clinicName = 'Klinik Rantai Berbayar';
        $clinicEmail = 'owner@klinikrantaiberbayar.test';
        $clinicPhone = '+60123456780';
        $clinicAddress = 'No. 2, Jalan Uji Rantai, 50000 Kuala Lumpur';

        $offering = $this->connection->table('commercial_catalogue_plan_offerings')
            ->where('capability_configuration_reference', 'package:syifa-basic')
            ->where('status', 'active')
            ->where('amount_minor', 29900)
            ->first(['id', 'billing_option_id', 'configuration_version']);
        self::assertNotNull($offering, 'The seeded Syifa Basic plan offering was not found.');

        // --- Clinic registration is drafted, submitted, and approved -------
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
                preferredSubdomain: 'klinik-rantai-berbayar',
                selectedWebsiteTemplate: 'SYIFA_ESSENTIAL',
            ),
        );

        $registration = $this->app->make(SubmitClinicRegistrationService::class)->execute(
            new SubmitClinicRegistrationCommand($applicantId, $registration->version, $now, $correlationId),
        );
        $registrationId = $registration->id;
        $tenantId = $registration->reservedTenantId;
        self::assertNotNull($tenantId);

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
        self::assertSame('approved', $this->connection->table('clinic_registrations')->where('id', $registrationId)->value('status'));

        // --- A paid checkout session is started: this creates the real
        // commercial_offers + payments (draft -> pending) + payment_attempts
        // rows, and calls the real (faked-HTTP) ToyyibPay provider. --------
        Http::fake([
            self::TOYYIBPAY_BASE_URL.'/index.php/api/createBill' => Http::response([['BillCode' => self::TOYYIBPAY_BILL_CODE]], 200),
            self::TOYYIBPAY_BASE_URL.'/index.php/api/getBillTransactions' => function (Request $request) {
                $paymentId = $this->connection->table('payments')->where('provider_payment_reference', self::TOYYIBPAY_BILL_CODE)->value('id');

                return Http::response([[
                    'billpaymentStatus' => '1',
                    'billpaymentAmount' => '299.00',
                    'billExternalReferenceNo' => $paymentId,
                ]], 200);
            },
        ]);

        $checkout = $this->app->make(StartPublicInitialAcquisitionCheckoutService::class)->execute(
            new StartPublicInitialAcquisitionCheckoutCommand($applicantId, (string) $offering->id, $now, $correlationId),
        );
        self::assertSame('ready', $checkout->status, 'Paid checkout session did not become ready.');

        $paymentId = $this->connection->table('payments')->where('clinic_registration_id', $registrationId)->value('id');
        self::assertIsString($paymentId, 'Checkout did not create a real payments row.');
        self::assertSame('pending', $this->connection->table('payments')->where('id', $paymentId)->value('status'));
        self::assertSame(self::TOYYIBPAY_BILL_CODE, $this->connection->table('payments')->where('id', $paymentId)->value('provider_payment_reference'));

        // --- A signed ToyyibPay webhook reports the payment succeeded -----
        $refno = 'r1';
        $status = '1';
        $hash = md5(self::TOYYIBPAY_SECRET_KEY.$status.$paymentId.$refno.'ok');
        $rawBody = http_build_query(['refno' => $refno, 'status' => $status, 'order_id' => $paymentId, 'billcode' => self::TOYYIBPAY_BILL_CODE, 'hash' => $hash]);

        $this->app->make(ReceivePaymentProviderWebhookService::class)->execute(
            new ProviderWebhookRequest('toyyibpay', $rawBody, [], new DateTimeImmutable, $correlationId),
        );

        $providerEventId = hash('sha256', implode('|', [self::TOYYIBPAY_BILL_CODE, $refno, $status]));
        $receiptId = $this->connection->table('payment_provider_webhook_receipts')
            ->where('provider_key', 'toyyibpay')->where('provider_event_id', $providerEventId)->value('id');
        self::assertIsString($receiptId, 'The webhook receipt was not registered.');

        // --- The webhook receipt is verified against the (faked) provider,
        // which registers a payment verification application. -------------
        $this->app->make(VerifyProviderWebhookReceiptService::class)->execute($receiptId);

        $verificationApplicationId = $this->connection->table('payment_verification_applications')->where('provider_webhook_receipt_id', $receiptId)->value('id');
        self::assertIsString($verificationApplicationId, 'A payment verification application was not registered.');

        // --- Applying the verification authoritatively marks the payment
        // succeeded and writes the real VerifiedPaymentSucceeded outbox
        // row. ----------------------------------------------------------
        $this->app->make(ApplyAuthoritativePaymentVerificationService::class)->execute($verificationApplicationId);
        self::assertSame('succeeded', $this->connection->table('payments')->where('id', $paymentId)->value('status'), 'Payment was not marked succeeded by verification.');
        self::assertSame('VerifiedPaymentSucceeded', $this->connection->table('payment_integration_outbox')->where('payment_id', $paymentId)->value('event_type'));

        // --- Publishing the payment outbox dispatches a real
        // PaymentIntegrationOutboxEvent through Laravel's real event
        // dispatcher, which the new, real
        // HandleVerifiedPaymentSucceededForSubscriptionActivation listener
        // (registered in SubscriptionBillingServiceProvider) is listening
        // for. This is the exact wiring Increment 5 closed. --------------
        $paymentPublisher = $this->app->make(PublishPaymentOutboxService::class);
        while ($paymentPublisher->publishNext()) {
        }

        $activationApplicationId = $this->connection->table('subscription_activation_applications')->where('payment_id', $paymentId)->value('id');
        self::assertIsString($activationApplicationId, 'The new listener did not register a subscription activation application for the verified payment.');
        self::assertSame($tenantId, $this->connection->table('subscription_activation_applications')->where('id', $activationApplicationId)->value('tenant_id'));

        // --- Draining the activation application creates the real
        // Subscription and its own SubscriptionActivated outbox row. ------
        $this->app->make(ActivateSubscriptionFromVerifiedPaymentService::class)->execute($activationApplicationId, $now);
        self::assertSame('active', $this->connection->table('subscriptions')->where('tenant_id', $tenantId)->value('status'), 'Subscription was not activated.');

        // --- The already-live Provisioning outbox/workflow machinery (the
        // same one the free-trial path uses) takes it from there. ---------
        $subscriptionOutboxPublisher = $this->app->make(PublishSubscriptionProvisioningOutboxService::class);
        while ($subscriptionOutboxPublisher->publishNext()) {
        }

        $provisioning = $this->app->make(ProcessProvisioningWorkflowService::class);
        while ($provisioning->processNext()) {
        }

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

        self::assertSame('provisioned', $this->connection->table('clinic_registrations')->where('id', $registrationId)->value('status'));

        // --- Super Admin assigns a Website Designer and establishes the
        // Clinic Owner (identical to the free-trial path from here) -------
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

        foreach (['service_setup', 'website_setup', 'booking_setup'] as $taskKey) {
            $this->completeDesignerTask($jobId, $taskKey, $designerId, $correlationId);
        }

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

        $this->completeDesignerTask($jobId, 'launch_readiness', $designerId, $correlationId);
        self::assertSame('ready_for_launch', $this->connection->table('onboarding_jobs')->where('id', $jobId)->value('status'));

        $publicationResult = $this->app->make(PublishWebsiteService::class)->handle(new PublishWebsiteCommand(
            $designerContext,
            $tenantId,
            $websiteId,
            (string) Str::uuid(),
            $this->websiteVersion($websiteId),
            $this->draftVersion($websiteId),
        ));
        self::assertSame('published', $publicationResult->lifecycle);

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
