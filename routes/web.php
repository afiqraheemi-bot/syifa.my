<?php

declare(strict_types=1);

use App\Http\Controllers\MarketingSeoController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\RootEntryController;
use App\Modules\Blog\Presentation\Http\Controllers\BlogDashboardController;
use App\Modules\Blog\Presentation\Http\Controllers\PublicBlogController;
use App\Modules\Booking\Presentation\Http\Controllers\ClinicOwnerBookingOperationController;
use App\Modules\Booking\Presentation\Http\Controllers\ClinicOwnerManualBookingController;
use App\Modules\PlatformAdministration\Presentation\Http\Controllers\PlatformEmailVerificationController;
use App\Modules\PlatformAdministration\Presentation\Http\Controllers\PlatformPasswordConfirmationController;
use App\Modules\PlatformAdministration\Presentation\Http\Controllers\PlatformPasswordResetController;
use App\Modules\PlatformAdministration\Presentation\Http\Controllers\PlatformSessionController;
use App\Modules\PlatformAdministration\Presentation\Http\Middleware\AuthenticatePlatformSessionMiddleware;
use App\Modules\SubscriptionBilling\Presentation\ApiVersion;
use App\Modules\SubscriptionBilling\Presentation\Http\Controllers\ClinicOwnerRenewalHostedCheckoutController;
use App\Modules\SubscriptionBilling\Presentation\Http\Controllers\CommercialCatalogueBillingOptionController;
use App\Modules\SubscriptionBilling\Presentation\Http\Controllers\CommercialCatalogueCapabilityDefinitionController;
use App\Modules\SubscriptionBilling\Presentation\Http\Controllers\CommercialCataloguePlanController;
use App\Modules\SubscriptionBilling\Presentation\Http\Controllers\CommercialCataloguePlanOfferingController;
use App\Modules\TenantManagement\Presentation\Http\Controllers\ClinicOwnerPasswordResetController;
use App\Modules\TenantManagement\Presentation\Http\Controllers\ClinicOwnerSessionController;
use App\Modules\TenantManagement\Presentation\Http\Controllers\ClinicOwnerSetupController;
use App\Modules\TenantManagement\Presentation\Http\Middleware\AuthenticateClinicOwnerSessionMiddleware;
use App\Modules\WebsiteBuilder\Presentation\Http\Controllers\AvailabilityController;
use App\Modules\WebsiteBuilder\Presentation\Http\Controllers\BookingController;
use App\Modules\WebsiteBuilder\Presentation\Http\Controllers\PublicLegalDocumentController;
use App\Modules\WebsiteBuilder\Presentation\Http\Controllers\PublicWebsiteController;
use App\Modules\WebsiteBuilder\Presentation\Http\Controllers\PublicWebsiteSeoController;
use App\Modules\WebsiteBuilder\Presentation\Http\Controllers\SuccessController;
use App\Support\Dashboard\Presentation\Http\Controllers\AuthenticatedDashboardController;
use App\Support\Dashboard\Presentation\Http\Controllers\BillingDocumentController;
use App\Support\Dashboard\Presentation\Http\Controllers\ClinicOwnerAccountController;
use App\Support\Dashboard\Presentation\Http\Controllers\ClinicOwnerBlogPreviewController;
use App\Support\Dashboard\Presentation\Http\Controllers\ClinicOwnerBookingDateOverrideController;
use App\Support\Dashboard\Presentation\Http\Controllers\ClinicOwnerBookingDetailController;
use App\Support\Dashboard\Presentation\Http\Controllers\ClinicOwnerBookingOverviewController;
use App\Support\Dashboard\Presentation\Http\Controllers\ClinicOwnerBookingPreviewController;
use App\Support\Dashboard\Presentation\Http\Controllers\ClinicOwnerBookingScheduleController;
use App\Support\Dashboard\Presentation\Http\Controllers\ClinicOwnerBookingWhatsAppSettingsController;
use App\Support\Dashboard\Presentation\Http\Controllers\ClinicOwnerBusinessHoursController;
use App\Support\Dashboard\Presentation\Http\Controllers\ClinicOwnerContactSettingsController;
use App\Support\Dashboard\Presentation\Http\Controllers\ClinicOwnerDraftPreviewController;
use App\Support\Dashboard\Presentation\Http\Controllers\ClinicOwnerServiceSetupController;
use App\Support\Dashboard\Presentation\Http\Controllers\ClinicOwnerSubscriptionController;
use App\Support\Dashboard\Presentation\Http\Controllers\ClinicOwnerSyifaAiController;
use App\Support\Dashboard\Presentation\Http\Controllers\ClinicOwnerWebsiteApprovalController;
use App\Support\Dashboard\Presentation\Http\Controllers\ClinicOwnerWebsiteAssetController;
use App\Support\Dashboard\Presentation\Http\Controllers\ClinicOwnerWebsiteContentOverviewController;
use App\Support\Dashboard\Presentation\Http\Controllers\ClinicOwnerWebsiteOverviewController;
use App\Support\Dashboard\Presentation\Http\Controllers\LaunchReadinessController;
use App\Support\Dashboard\Presentation\Http\Controllers\NotificationHistoryController;
use App\Support\Dashboard\Presentation\Http\Controllers\OnboardingTaskController;
use App\Support\Dashboard\Presentation\Http\Controllers\ReportsController;
use App\Support\Dashboard\Presentation\Http\Controllers\SuperAdminAuditViewerController;
use App\Support\Dashboard\Presentation\Http\Controllers\SuperAdminBillingOverviewController;
use App\Support\Dashboard\Presentation\Http\Controllers\SuperAdminCommercialBillingOptionOperationController;
use App\Support\Dashboard\Presentation\Http\Controllers\SuperAdminCommercialCapabilityOperationController;
use App\Support\Dashboard\Presentation\Http\Controllers\SuperAdminCommercialManagementController;
use App\Support\Dashboard\Presentation\Http\Controllers\SuperAdminCommercialOfferingOperationController;
use App\Support\Dashboard\Presentation\Http\Controllers\SuperAdminCommercialPackageOperationController;
use App\Support\Dashboard\Presentation\Http\Controllers\SuperAdminCommercialPlanOperationController;
use App\Support\Dashboard\Presentation\Http\Controllers\SuperAdminOnboardingController;
use App\Support\Dashboard\Presentation\Http\Controllers\SuperAdminPaymentProviderController;
use App\Support\Dashboard\Presentation\Http\Controllers\SuperAdminRegistrationReviewController;
use App\Support\Dashboard\Presentation\Http\Controllers\SuperAdminSubscriptionDetailController;
use App\Support\Dashboard\Presentation\Http\Controllers\SuperAdminSubscriptionOperationController;
use App\Support\Dashboard\Presentation\Http\Controllers\SuperAdminSyifaAiUsageOverviewController;
use App\Support\Dashboard\Presentation\Http\Controllers\SuperAdminTenantOverviewController;
use App\Support\Dashboard\Presentation\Http\Controllers\WebsiteDesignerBlogPreviewController;
use App\Support\Dashboard\Presentation\Http\Controllers\WebsiteDesignerBookingPreviewController;
use App\Support\Dashboard\Presentation\Http\Controllers\WebsiteDesignerCustomDomainController;
use App\Support\Dashboard\Presentation\Http\Controllers\WebsiteDesignerDraftContentController;
use App\Support\Dashboard\Presentation\Http\Controllers\WebsiteDesignerDraftPreviewController;
use App\Support\Dashboard\Presentation\Http\Controllers\WebsiteDesignerJobDetailController;
use App\Support\Dashboard\Presentation\Http\Controllers\WebsiteDesignerOnboardingQueueController;
use App\Support\Dashboard\Presentation\Http\Controllers\WebsiteDesignerPublishWebsiteController;
use App\Support\Dashboard\Presentation\Http\Controllers\WebsiteDesignerSyifaAiController;
use App\Support\Dashboard\Presentation\Http\Controllers\WebsiteDesignerWebsiteAddressController;
use App\Support\Dashboard\Presentation\Http\Controllers\WebsiteDesignerWebsiteAssetController;
use App\Support\Dashboard\Presentation\Http\Middleware\RedirectMisdirectedWebsitePreviewRequest;
use App\Support\Marketing\Presentation\Http\Controllers\MarketingHomeController;
use App\Support\Marketing\Presentation\Http\Controllers\TemplatePreviewController;
use App\Support\PublicWebsite\Presentation\Http\Controllers\PublicWebsiteAssetController;
use Illuminate\Support\Facades\Route;

// Application entry point (distinct from a tenant's public Website): only
// the configured app host and the common local-development aliases match
// here; every other host falls through to the tenant Website route below,
// unchanged.
// Each host below gets its own registration, but a route name may only be
// claimed once: naming every one of them makes `route:cache` fail outright with
// "Another route has already been assigned name [root]". The first host owns the
// name and therefore URL generation; the rest match without one.
foreach (RootEntryController::tenantAdminBaseDomains() as $index => $tenantAdminBaseDomain) {
    // `/login`, not `/`: with `syifa.my` among the admin base domains this
    // pattern matches every tenant subdomain, and `/` there belongs to that
    // tenant's public Website. Claiming a path instead of the host root lets one
    // hostname serve the patient-facing site and its staff login side by side.
    $tenantAdminLogin = Route::domain('{tenantAdminLabel}.'.$tenantAdminBaseDomain)
        ->get('/login', RootEntryController::class)
        ->where('tenantAdminLabel', '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?');

    if ($index === 0) {
        $tenantAdminLogin->name('clinic-owner.login');
    }
}
foreach (RootEntryController::appEntryHosts() as $index => $appEntryHost) {
    $appEntry = Route::domain($appEntryHost);

    $marketingHome = $appEntry->get('/', MarketingHomeController::class);
    $loginEntry = $appEntry->get('/login', RootEntryController::class);

    if ($index === 0) {
        $marketingHome->name('root');
        $loginEntry->name('login');
    }

    $appEntry->get('/robots.txt', [MarketingSeoController::class, 'robots']);
    $appEntry->get('/sitemap.xml', [MarketingSeoController::class, 'sitemap']);
}
Route::get('/', PublicWebsiteController::class)->name('public-website.home');
Route::get('/blog', [PublicBlogController::class, 'index'])->middleware('throttle:public.default')->name('public-blog.index');
Route::get('/blog/{slug}', [PublicBlogController::class, 'show'])->where('slug', '[a-z0-9-]+')->middleware('throttle:public.default')->name('public-blog.show');
Route::get('/robots.txt', [PublicWebsiteSeoController::class, 'robots']);
Route::get('/sitemap.xml', [PublicWebsiteSeoController::class, 'sitemap']);
Route::get('/clinic-owner/setup/{token}', [ClinicOwnerSetupController::class, 'show'])
    ->middleware('throttle:public.default')
    ->name('clinic-owner.setup');
Route::post('/clinic-owner/setup', [ClinicOwnerSetupController::class, 'complete'])
    ->middleware('throttle:platform.password-reset')
    ->name('clinic-owner.setup.complete');
Route::get('/platform/password/reset/{token}', [PlatformPasswordResetController::class, 'show'])
    ->middleware('throttle:public.default')
    ->name('platform.password.reset');
Route::get('/privacy', [PublicLegalDocumentController::class, 'privacy'])->name('public-website.privacy');
Route::get('/terms', [PublicLegalDocumentController::class, 'terms'])->name('public-website.terms');
Route::get('/templates/preview/{slug}', TemplatePreviewController::class)
    ->middleware('throttle:public.default')
    ->name('templates.preview');
Route::get('/assets/{assetId}', PublicWebsiteAssetController::class)
    ->whereUuid('assetId')
    ->middleware('throttle:public.default')
    ->name('public-website.assets.show');
Route::get('/dashboard', AuthenticatedDashboardController::class)
    ->middleware('authorize.context:authenticated,clinic_owner,website_designer,super_admin')
    ->name('dashboard');
Route::get('/dashboard/account', [ClinicOwnerAccountController::class, 'show'])
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('dashboard.account');
Route::redirect('/account', '/dashboard/account', 302);
Route::redirect('/account-security', '/dashboard/account', 302);
Route::redirect('/account/security', '/dashboard/account', 302);
Route::redirect('/dashboard/account-security', '/dashboard/account', 302);
Route::patch('/dashboard/account/profile', [ClinicOwnerAccountController::class, 'updateProfile'])
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('dashboard.account.profile.update');
Route::put('/dashboard/account/password', [ClinicOwnerAccountController::class, 'updatePassword'])
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('dashboard.account.password.update');
Route::get('/dashboard/blog', [BlogDashboardController::class, 'index'])
    ->middleware('authorize.context:authenticated,clinic_owner,super_admin')->name('dashboard.blog');
Route::get('/dashboard/blog/create', [BlogDashboardController::class, 'editor'])
    ->middleware('authorize.context:authenticated,clinic_owner,super_admin')->name('dashboard.blog.create');
Route::post('/dashboard/blog', [BlogDashboardController::class, 'store'])
    ->middleware('authorize.context:authenticated,clinic_owner')->name('dashboard.blog.store');
Route::get('/dashboard/blog/{postId}', [BlogDashboardController::class, 'editor'])->whereUuid('postId')
    ->middleware('authorize.context:authenticated,clinic_owner,super_admin')->name('dashboard.blog.edit');
Route::patch('/dashboard/blog/{postId}', [BlogDashboardController::class, 'update'])->whereUuid('postId')
    ->middleware('authorize.context:authenticated,clinic_owner')->name('dashboard.blog.update');
Route::post('/dashboard/blog/{postId}/transition', [BlogDashboardController::class, 'transition'])->whereUuid('postId')
    ->middleware('authorize.context:authenticated,clinic_owner,super_admin')->name('dashboard.blog.transition');
Route::get('/api/v1/onboarding-jobs/{jobId}/launch-readiness', LaunchReadinessController::class)
    ->whereUuid('jobId')
    ->middleware('authorize.context:authenticated,clinic_owner,website_designer,super_admin')
    ->name('onboarding.launch-readiness.show');
Route::get('/dashboard/onboarding', WebsiteDesignerOnboardingQueueController::class)
    ->middleware('authorize.context:platform_identity,website_designer')
    ->name('dashboard.onboarding');
Route::match(['get', 'patch'], '/dashboard/onboarding/{jobId}', WebsiteDesignerJobDetailController::class)
    ->whereUuid('jobId')
    ->middleware('authorize.context:platform_identity,website_designer')
    ->name('dashboard.onboarding.show');
Route::get('/dashboard/onboarding/{jobId}/blog', [BlogDashboardController::class, 'designerIndex'])
    ->whereUuid('jobId')->middleware('authorize.context:platform_identity,website_designer')
    ->name('dashboard.onboarding.blog');
Route::get('/dashboard/onboarding/{jobId}/blog/create', [BlogDashboardController::class, 'designerEditor'])
    ->whereUuid('jobId')->middleware('authorize.context:platform_identity,website_designer')
    ->name('dashboard.onboarding.blog.create');
Route::post('/dashboard/onboarding/{jobId}/blog', [BlogDashboardController::class, 'designerStore'])
    ->whereUuid('jobId')->middleware('authorize.context:platform_identity,website_designer')
    ->name('dashboard.onboarding.blog.store');
Route::get('/dashboard/onboarding/{jobId}/blog/{postId}', [BlogDashboardController::class, 'designerEditor'])
    ->whereUuid(['jobId', 'postId'])->middleware('authorize.context:platform_identity,website_designer')
    ->name('dashboard.onboarding.blog.edit');
Route::patch('/dashboard/onboarding/{jobId}/blog/{postId}', [BlogDashboardController::class, 'designerUpdate'])
    ->whereUuid(['jobId', 'postId'])->middleware('authorize.context:platform_identity,website_designer')
    ->name('dashboard.onboarding.blog.update');
Route::post('/dashboard/onboarding/{jobId}/blog/{postId}/transition', [BlogDashboardController::class, 'designerTransition'])
    ->whereUuid(['jobId', 'postId'])->middleware('authorize.context:platform_identity,website_designer')
    ->name('dashboard.onboarding.blog.transition');
Route::patch('/dashboard/onboarding/{jobId}/tasks/{taskId}', OnboardingTaskController::class)
    ->whereUuid(['jobId', 'taskId'])
    ->middleware('authorize.context:platform_identity,website_designer')
    ->name('dashboard.onboarding.tasks.update');
Route::get('/dashboard/onboarding/{jobId}/preview', WebsiteDesignerDraftPreviewController::class)
    ->whereUuid('jobId')
    ->middleware([
        RedirectMisdirectedWebsitePreviewRequest::class,
        'authorize.context:platform_identity,website_designer',
    ])
    ->name('dashboard.onboarding.preview');
Route::get('/dashboard/onboarding/{jobId}/preview/blog', [WebsiteDesignerBlogPreviewController::class, 'index'])
    ->whereUuid('jobId')
    ->middleware('authorize.context:platform_identity,website_designer')
    ->name('dashboard.onboarding.blog-preview.index');
Route::get('/dashboard/onboarding/{jobId}/preview/blog/{slug}', [WebsiteDesignerBlogPreviewController::class, 'show'])
    ->whereUuid('jobId')
    ->where('slug', '[a-z0-9-]+')
    ->middleware('authorize.context:platform_identity,website_designer')
    ->name('dashboard.onboarding.blog-preview.show');
Route::match(['get', 'post'], '/dashboard/onboarding/{jobId}/preview/booking', WebsiteDesignerBookingPreviewController::class)
    ->whereUuid('jobId')
    ->middleware([
        RedirectMisdirectedWebsitePreviewRequest::class,
        'authorize.context:platform_identity,website_designer',
    ])
    ->name('dashboard.onboarding.booking-preview');
Route::post('/dashboard/onboarding/{jobId}/publish', WebsiteDesignerPublishWebsiteController::class)
    ->whereUuid('jobId')
    ->middleware('authorize.context:platform_identity,website_designer')
    ->name('dashboard.onboarding.publish');
Route::match(['get', 'post'], '/dashboard/onboarding/{jobId}/website-address', WebsiteDesignerWebsiteAddressController::class)
    ->whereUuid('jobId')
    ->middleware('authorize.context:platform_identity,website_designer')
    ->name('dashboard.onboarding.website-address');
Route::prefix('/dashboard/onboarding/{jobId}/custom-domain')
    ->whereUuid('jobId')
    ->middleware('authorize.context:platform_identity,website_designer')
    ->name('dashboard.onboarding.custom-domain')
    ->group(function (): void {
        Route::get('/', [WebsiteDesignerCustomDomainController::class, 'index'])->name('');
        Route::post('/', [WebsiteDesignerCustomDomainController::class, 'store'])->name('.store');
        Route::post('/verify', [WebsiteDesignerCustomDomainController::class, 'verify'])->name('.verify');
        Route::post('/activate', [WebsiteDesignerCustomDomainController::class, 'activate'])->name('.activate');
        Route::post('/detach', [WebsiteDesignerCustomDomainController::class, 'detach'])->name('.detach');
    });
Route::prefix('/api/v1/platform/onboarding/{jobId}/website-draft')
    ->whereUuid('jobId')
    ->middleware('authorize.context:platform_identity,website_designer')
    ->name('website-designer.website-draft.')
    ->group(function (): void {
        Route::get('/', [WebsiteDesignerDraftContentController::class, 'show'])->name('show');
        Route::patch('/', [WebsiteDesignerDraftContentController::class, 'update'])->name('update');
    });
Route::post('/api/v1/platform/onboarding/{jobId}/website-assets', WebsiteDesignerWebsiteAssetController::class)
    ->whereUuid('jobId')
    ->middleware('authorize.context:platform_identity,website_designer')
    ->name('website-designer.website-assets.store');
Route::post('/api/v1/platform/onboarding/{jobId}/syifa-ai', WebsiteDesignerSyifaAiController::class)
    ->whereUuid('jobId')
    ->middleware(['authorize.context:platform_identity,website_designer', 'throttle:syifa-ai'])
    ->name('website-designer.syifa-ai.assist');
Route::get('/dashboard/tenants', SuperAdminTenantOverviewController::class)
    ->middleware('authorize.context:platform_identity,super_admin')
    ->name('dashboard.tenants');
Route::get('/dashboard/audit', SuperAdminAuditViewerController::class)
    ->middleware('authorize.context:platform_identity,super_admin')
    ->name('dashboard.audit');
Route::get('/dashboard/notifications', NotificationHistoryController::class)
    ->middleware('authorize.context:authenticated,clinic_owner,super_admin')
    ->name('dashboard.notifications');
Route::get('/dashboard/reports', ReportsController::class)
    ->middleware('authorize.context:authenticated,clinic_owner,website_designer,super_admin')
    ->name('dashboard.reports');
Route::get('/dashboard/registrations', [SuperAdminRegistrationReviewController::class, 'index'])
    ->middleware('authorize.context:platform_identity,super_admin')
    ->name('dashboard.registrations');
Route::post('/dashboard/registrations/{registrationId}/review', [SuperAdminRegistrationReviewController::class, 'review'])
    ->whereUuid('registrationId')
    ->middleware('authorize.context:platform_identity,super_admin')
    ->name('dashboard.registrations.review');
Route::post('/dashboard/registrations/{registrationId}/decision', [SuperAdminRegistrationReviewController::class, 'decide'])
    ->whereUuid('registrationId')
    ->middleware('authorize.context:platform_identity,super_admin')
    ->name('dashboard.registrations.decision');
Route::post('/dashboard/registrations/{registrationId}/activate-trial', [SuperAdminRegistrationReviewController::class, 'activateTrial'])
    ->whereUuid('registrationId')
    ->middleware('authorize.context:platform_identity,super_admin')
    ->name('dashboard.registrations.activate-trial');
Route::patch('/dashboard/registrations/{registrationId}', [SuperAdminRegistrationReviewController::class, 'update'])
    ->whereUuid('registrationId')
    ->middleware('authorize.context:platform_identity,super_admin')
    ->name('dashboard.registrations.update');
Route::delete('/dashboard/registrations/{registrationId}', [SuperAdminRegistrationReviewController::class, 'archive'])
    ->whereUuid('registrationId')
    ->middleware('authorize.context:platform_identity,super_admin')
    ->name('dashboard.registrations.archive');
Route::get('/dashboard/onboarding-management', [SuperAdminOnboardingController::class, 'index'])
    ->middleware('authorize.context:platform_identity,super_admin')
    ->name('dashboard.onboarding-management');
Route::post('/dashboard/onboarding-management/{jobId}/assignment', [SuperAdminOnboardingController::class, 'assign'])
    ->whereUuid('jobId')
    ->middleware('authorize.context:platform_identity,super_admin')
    ->name('dashboard.onboarding-management.assign');
Route::post('/dashboard/onboarding-management/{jobId}/reassignment', [SuperAdminOnboardingController::class, 'reassign'])
    ->whereUuid('jobId')
    ->middleware('authorize.context:platform_identity,super_admin')
    ->name('dashboard.onboarding-management.reassign');
Route::post('/dashboard/onboarding-management/{jobId}/lifecycle', [SuperAdminOnboardingController::class, 'lifecycle'])
    ->whereUuid('jobId')
    ->middleware('authorize.context:platform_identity,super_admin')
    ->name('dashboard.onboarding-management.lifecycle');
Route::patch('/dashboard/onboarding-management/{jobId}/tasks/{taskId}', OnboardingTaskController::class)
    ->whereUuid(['jobId', 'taskId'])
    ->middleware('authorize.context:platform_identity,super_admin')
    ->name('dashboard.onboarding-management.tasks.update');
Route::post('/dashboard/onboarding-management/tenants/{tenantId}/owner', [SuperAdminOnboardingController::class, 'establishOwner'])
    ->whereUuid('tenantId')
    ->middleware('authorize.context:platform_identity,super_admin')
    ->name('dashboard.onboarding-management.owner');
Route::get('/dashboard/billing', SuperAdminBillingOverviewController::class)
    ->middleware('authorize.context:platform_identity,super_admin')
    ->name('dashboard.billing');
Route::get('/dashboard/payment-providers', SuperAdminPaymentProviderController::class)
    ->middleware('authorize.context:platform_identity,super_admin')
    ->name('dashboard.payment-providers');
Route::get('/dashboard/syifa-ai-usage', SuperAdminSyifaAiUsageOverviewController::class)
    ->middleware('authorize.context:platform_identity,super_admin')
    ->name('dashboard.syifa-ai-usage');
Route::prefix('/dashboard/commercial')
    ->middleware('authorize.context:platform_identity,super_admin')
    ->name('dashboard.commercial')
    ->group(function (): void {
        Route::get('/website-preview', [MarketingHomeController::class, 'preview'])
            ->name('.website-preview');
        Route::get('/', [SuperAdminCommercialManagementController::class, 'index'])
            ->name('');
        Route::get('/packages/create', [SuperAdminCommercialManagementController::class, 'createPackage'])
            ->name('.packages.create');
        Route::get('/plans/create', [SuperAdminCommercialManagementController::class, 'createPlan'])
            ->name('.plans.create');
        Route::get('/billing-options/create', [SuperAdminCommercialManagementController::class, 'createBillingOption'])
            ->name('.billing-options.create');
        Route::get('/capabilities/create', [SuperAdminCommercialManagementController::class, 'createCapability'])
            ->name('.capabilities.create');
        Route::get('/capabilities/{capabilityId}/edit', [SuperAdminCommercialManagementController::class, 'editCapability'])
            ->whereUuid('capabilityId')
            ->name('.capabilities.edit');
        Route::get('/billing-options/{billingOptionId}/edit', [SuperAdminCommercialManagementController::class, 'editBillingOption'])
            ->whereUuid('billingOptionId')
            ->name('.billing-options.edit');
        Route::get('/plans/{planId}/edit', [SuperAdminCommercialManagementController::class, 'editPlan'])
            ->whereUuid('planId')
            ->name('.plans.edit');
        Route::get('/plans/{planId}/offerings/create', [SuperAdminCommercialManagementController::class, 'createOffering'])
            ->whereUuid('planId')
            ->name('.plans.offerings.create');
        Route::get('/plans/{planId}/offerings/{offeringId}/edit', [SuperAdminCommercialManagementController::class, 'editOffering'])
            ->whereUuid(['planId', 'offeringId'])
            ->name('.plans.offerings.edit');
        Route::get('/plans/{planId}', [SuperAdminCommercialManagementController::class, 'showPlan'])
            ->whereUuid('planId')
            ->name('.plans.show');
        Route::get('/plans/{planId}/offerings/{offeringId}', [SuperAdminCommercialManagementController::class, 'showOffering'])
            ->whereUuid(['planId', 'offeringId'])
            ->name('.plans.offerings.show');
        Route::post('/plans', [SuperAdminCommercialPlanOperationController::class, 'store'])
            ->name('.plans.store');
        Route::post('/packages', [SuperAdminCommercialPackageOperationController::class, 'store'])
            ->name('.packages.store');
        Route::post('/billing-options', [SuperAdminCommercialBillingOptionOperationController::class, 'store'])
            ->name('.billing-options.store');
        Route::patch('/billing-options/{billingOptionId}', [SuperAdminCommercialBillingOptionOperationController::class, 'update'])
            ->whereUuid('billingOptionId')
            ->name('.billing-options.update');
        Route::post('/capabilities', [SuperAdminCommercialCapabilityOperationController::class, 'store'])
            ->name('.capabilities.store');
        Route::patch('/capabilities/{capabilityId}', [SuperAdminCommercialCapabilityOperationController::class, 'update'])
            ->whereUuid('capabilityId')
            ->name('.capabilities.update');
        Route::post('/capabilities/{capabilityId}/activate', [SuperAdminCommercialCapabilityOperationController::class, 'activate'])
            ->whereUuid('capabilityId')
            ->name('.capabilities.activate');
        Route::post('/capabilities/{capabilityId}/deprecate', [SuperAdminCommercialCapabilityOperationController::class, 'deprecate'])
            ->whereUuid('capabilityId')
            ->name('.capabilities.deprecate');
        Route::post('/capabilities/{capabilityId}/retire', [SuperAdminCommercialCapabilityOperationController::class, 'retire'])
            ->whereUuid('capabilityId')
            ->name('.capabilities.retire');
        Route::patch('/plans/{planId}', [SuperAdminCommercialPlanOperationController::class, 'update'])
            ->whereUuid('planId')
            ->name('.plans.update');
        Route::post('/plans/{planId}/publish', [SuperAdminCommercialPlanOperationController::class, 'publish'])
            ->whereUuid('planId')
            ->name('.plans.publish');
        Route::post('/plans/{planId}/unavailable', [SuperAdminCommercialPlanOperationController::class, 'unavailable'])
            ->whereUuid('planId')
            ->name('.plans.unavailable');
        Route::post('/plans/{planId}/grandfather', [SuperAdminCommercialPlanOperationController::class, 'grandfather'])
            ->whereUuid('planId')
            ->name('.plans.grandfather');
        Route::post('/plans/{planId}/retire', [SuperAdminCommercialPlanOperationController::class, 'retire'])
            ->whereUuid('planId')
            ->name('.plans.retire');
        Route::post('/offerings', [SuperAdminCommercialOfferingOperationController::class, 'store'])
            ->name('.offerings.store');
        Route::patch('/offerings/{offeringId}', [SuperAdminCommercialOfferingOperationController::class, 'update'])
            ->whereUuid('offeringId')
            ->name('.offerings.update');
        Route::post('/offerings/{offeringId}/activate', [SuperAdminCommercialOfferingOperationController::class, 'activate'])
            ->whereUuid('offeringId')
            ->name('.offerings.activate');
        Route::post('/offerings/{offeringId}/unavailable', [SuperAdminCommercialOfferingOperationController::class, 'unavailable'])
            ->whereUuid('offeringId')
            ->name('.offerings.unavailable');
        Route::post('/offerings/{offeringId}/grandfather', [SuperAdminCommercialOfferingOperationController::class, 'grandfather'])
            ->whereUuid('offeringId')
            ->name('.offerings.grandfather');
        Route::post('/offerings/{offeringId}/retire', [SuperAdminCommercialOfferingOperationController::class, 'retire'])
            ->whereUuid('offeringId')
            ->name('.offerings.retire');
    });
Route::get('/dashboard/billing/subscriptions/{subscriptionId}', SuperAdminSubscriptionDetailController::class)
    ->whereUuid('subscriptionId')
    ->middleware('authorize.context:platform_identity,super_admin')
    ->name('dashboard.billing.subscriptions.show');
Route::get('/dashboard/billing/invoices/{paymentId}', [BillingDocumentController::class, 'superAdminInvoice'])
    ->whereUuid('paymentId')
    ->middleware('authorize.context:platform_identity,super_admin')
    ->name('dashboard.billing.invoices.show');
Route::get('/dashboard/billing/receipts/{paymentId}', [BillingDocumentController::class, 'superAdminReceipt'])
    ->whereUuid('paymentId')
    ->middleware('authorize.context:platform_identity,super_admin')
    ->name('dashboard.billing.receipts.show');
Route::prefix('/dashboard/billing/subscriptions/{subscriptionId}')
    ->whereUuid('subscriptionId')
    ->middleware('authorize.context:platform_identity,super_admin')
    ->name('dashboard.billing.subscriptions.')
    ->group(function (): void {
        Route::post('/renew', [SuperAdminSubscriptionOperationController::class, 'renew'])->name('renew');
        Route::post('/change-plan', [SuperAdminSubscriptionOperationController::class, 'changePlan'])->name('change-plan');
        Route::post('/auto-renew/enable', [SuperAdminSubscriptionOperationController::class, 'enableAutoRenew'])->name('auto-renew.enable');
        Route::post('/auto-renew/disable', [SuperAdminSubscriptionOperationController::class, 'disableAutoRenew'])->name('auto-renew.disable');
    });
Route::get('/dashboard/website', ClinicOwnerWebsiteOverviewController::class)
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('dashboard.website');
Route::post('/dashboard/website/approval', ClinicOwnerWebsiteApprovalController::class)
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('dashboard.website.approval');
Route::get('/dashboard/website/preview', ClinicOwnerDraftPreviewController::class)
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('dashboard.website.preview');
Route::get('/dashboard/website/preview/blog', [ClinicOwnerBlogPreviewController::class, 'index'])
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('dashboard.website.blog-preview.index');
Route::get('/dashboard/website/preview/blog/{slug}', [ClinicOwnerBlogPreviewController::class, 'show'])
    ->where('slug', '[a-z0-9-]+')
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('dashboard.website.blog-preview.show');
Route::match(['get', 'post'], '/dashboard/website/preview/booking', ClinicOwnerBookingPreviewController::class)
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('dashboard.website.booking-preview');
Route::patch('/dashboard/website/onboarding-tasks/{jobId}/{taskId}', OnboardingTaskController::class)
    ->whereUuid(['jobId', 'taskId'])
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('dashboard.website.onboarding-tasks.update');
Route::get('/dashboard/website/content', ClinicOwnerWebsiteContentOverviewController::class)
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('dashboard.website.content');
Route::patch('/dashboard/website/content', [ClinicOwnerWebsiteContentOverviewController::class, 'update'])
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('dashboard.website.content.update');
Route::patch('/dashboard/website/blog-visibility', [ClinicOwnerWebsiteContentOverviewController::class, 'updateBlogVisibility'])
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('dashboard.website.blog-visibility.update');
Route::patch('/dashboard/website/contact', ClinicOwnerContactSettingsController::class)
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('dashboard.website.contact.update');
Route::get('/api/v1/clinic-owner/website-draft', [ClinicOwnerWebsiteContentOverviewController::class, 'showDraft'])
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('clinic-owner.website-draft.show');
Route::patch('/api/v1/clinic-owner/website-draft', [ClinicOwnerWebsiteContentOverviewController::class, 'updateDraft'])
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('clinic-owner.website-draft.update');
Route::post('/api/v1/clinic-owner/syifa-ai', ClinicOwnerSyifaAiController::class)
    ->middleware(['authorize.context:clinic_owner,clinic_owner', 'throttle:syifa-ai'])
    ->name('clinic-owner.syifa-ai.assist');
Route::post('/api/v1/clinic-owner/website-assets', ClinicOwnerWebsiteAssetController::class)
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('clinic-owner.website-assets.store');
Route::get('/dashboard/bookings', ClinicOwnerBookingOverviewController::class)
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('dashboard.bookings');
Route::patch('/dashboard/bookings/schedule', ClinicOwnerBookingScheduleController::class)
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('dashboard.bookings.schedule.update');
Route::patch('/dashboard/bookings/whatsapp-settings', ClinicOwnerBookingWhatsAppSettingsController::class)
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('dashboard.bookings.whatsapp-settings.update');
Route::patch('/dashboard/bookings/business-hours', ClinicOwnerBusinessHoursController::class)
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('dashboard.bookings.business-hours.update');
Route::post('/dashboard/bookings/date-overrides', [ClinicOwnerBookingDateOverrideController::class, 'store'])
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('dashboard.bookings.date-overrides.store');
Route::delete('/dashboard/bookings/date-overrides/{localDate}', [ClinicOwnerBookingDateOverrideController::class, 'destroy'])
    ->where('localDate', '\\d{4}-\\d{2}-\\d{2}')
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('dashboard.bookings.date-overrides.destroy');
Route::prefix('/dashboard/services')
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('dashboard.services')
    ->group(function (): void {
        Route::get('/', [ClinicOwnerServiceSetupController::class, 'index'])->name('');
        Route::post('/', [ClinicOwnerServiceSetupController::class, 'store'])->name('.store');
        Route::patch('/{serviceId}', [ClinicOwnerServiceSetupController::class, 'update'])->whereUuid('serviceId')->name('.update');
        Route::patch('/{serviceId}/status', [ClinicOwnerServiceSetupController::class, 'status'])->whereUuid('serviceId')->name('.status');
    });
Route::get('/dashboard/subscription', ClinicOwnerSubscriptionController::class)
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('dashboard.subscription');
Route::get('/dashboard/subscription/invoices/{paymentId}', [BillingDocumentController::class, 'clinicOwnerInvoice'])
    ->whereUuid('paymentId')
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('dashboard.subscription.invoices.show');
Route::get('/dashboard/subscription/receipts/{paymentId}', [BillingDocumentController::class, 'clinicOwnerReceipt'])
    ->whereUuid('paymentId')
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('dashboard.subscription.receipts.show');
Route::post('/dashboard/subscription/renewal-checkout', ClinicOwnerRenewalHostedCheckoutController::class)
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('dashboard.subscription.renewal-checkout');
Route::post('/dashboard/bookings', ClinicOwnerManualBookingController::class)
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('dashboard.bookings.store');
Route::get('/dashboard/bookings/{bookingId}', ClinicOwnerBookingDetailController::class)
    ->whereUuid('bookingId')
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('dashboard.bookings.show');
Route::prefix('/dashboard/bookings/{bookingId}')
    ->whereUuid('bookingId')
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('dashboard.bookings.')
    ->group(function (): void {
        Route::post('/confirm', [ClinicOwnerBookingOperationController::class, 'confirm'])->name('confirm');
        Route::post('/cancel', [ClinicOwnerBookingOperationController::class, 'cancel'])->name('cancel');
        Route::post('/complete', [ClinicOwnerBookingOperationController::class, 'complete'])->name('complete');
        Route::patch('/reschedule', [ClinicOwnerBookingOperationController::class, 'reschedule'])->name('reschedule');
    });

// Public Booking Delivery (ADR-029, amended by ADR-030/031) — finite, additive
// route set. No wildcard, no reference/bookingId-shaped parameter anywhere.
Route::prefix('booking')
    ->middleware('throttle:public.default')
    ->name('public-website.booking.')
    ->group(function (): void {
        Route::get('/', [BookingController::class, 'landing'])->name('start');
        Route::get('/service', [BookingController::class, 'service'])->name('service');
        Route::post('/service', [BookingController::class, 'updateService'])->name('service.update');
        Route::get('/date', [BookingController::class, 'date'])->name('date');
        Route::post('/date', [BookingController::class, 'updateDate'])->name('date.update');
        Route::get('/availability', [AvailabilityController::class, 'forDate'])->name('availability');
        Route::get('/details', [BookingController::class, 'details'])->name('details');
        Route::post('/details', [BookingController::class, 'updateDetails'])->name('details.update');
        Route::get('/review', [BookingController::class, 'review'])->name('review');
        Route::post('/', [BookingController::class, 'submit'])->middleware('throttle:booking-submission')->name('submit');
        Route::get('/success/{token}', [SuccessController::class, 'show'])->name('success');
    });

if ((bool) config('operations.enabled', true)) {
    Route::prefix((string) config('operations.prefix', 'operations'))
        ->middleware('throttle:public.default')
        ->name('operations.')
        ->group(function (): void {
            Route::get((string) config('operations.endpoints.health', 'health'), [OperationsController::class, 'health'])
                ->name('health');
            Route::get((string) config('operations.endpoints.ready', 'ready'), [OperationsController::class, 'ready'])
                ->name('ready');
            Route::get((string) config('operations.endpoints.live', 'live'), [OperationsController::class, 'live'])
                ->name('live');
            Route::get((string) config('operations.endpoints.info', 'info'), [OperationsController::class, 'info'])
                ->name('info');
            Route::get((string) config('operations.endpoints.build', 'build'), [OperationsController::class, 'build'])
                ->name('build');
            Route::get((string) config('operations.endpoints.version', 'version'), [OperationsController::class, 'version'])
                ->name('version');
            Route::get((string) config('operations.endpoints.release', 'release'), [OperationsController::class, 'release'])
                ->name('release');
        });

    Route::prefix('health')
        ->middleware('throttle:public.default')
        ->name('health.')
        ->group(function (): void {
            Route::get('/live', [OperationsController::class, 'live'])->name('live');
            Route::get('/ready', [OperationsController::class, 'ready'])->name('ready');
        });
    Route::get('/build', [OperationsController::class, 'build'])
        ->middleware('throttle:public.default')
        ->name('build');
    Route::get('/version', [OperationsController::class, 'version'])
        ->middleware('throttle:public.default')
        ->name('version');
    Route::get('/release', [OperationsController::class, 'release'])
        ->middleware('throttle:public.default')
        ->name('release');
}

Route::prefix('api/v1')
    ->group(function (): void {
        Route::post('/password/forgot', ClinicOwnerPasswordResetController::class)
            ->middleware('throttle:platform.password-reset')
            ->name('clinic-owner.password.forgot');
        Route::post('/sessions', [ClinicOwnerSessionController::class, 'store'])
            ->middleware([
                'guest.guard:clinic_owner',
                'guest.guard:platform_identity',
                'throttle:clinic-owner-session',
            ]);
        Route::get('/sessions/current', [ClinicOwnerSessionController::class, 'show'])
            ->middleware([
                AuthenticateClinicOwnerSessionMiddleware::class,
                'authorize.context:clinic_owner,clinic_owner',
            ]);
        Route::delete('/sessions/current', [ClinicOwnerSessionController::class, 'destroy']);
    });

Route::prefix('api/v1/platform/sessions')
    ->group(function (): void {
        Route::post('/', [PlatformSessionController::class, 'store'])
            ->middleware([
                'guest.guard:platform_identity',
                'guest.guard:clinic_owner',
                'throttle:platform.login',
            ]);
        Route::post('/mfa', [PlatformSessionController::class, 'completeMfa'])
            ->middleware('throttle:platform.login')
            ->name('platform-sessions.mfa');
        Route::get('/current', [PlatformSessionController::class, 'show'])
            ->middleware([
                'throttle:platform.session',
                AuthenticatePlatformSessionMiddleware::class,
                'authorize.context:platform_identity,super_admin,website_designer',
            ]);
        Route::delete('/current', [PlatformSessionController::class, 'destroy'])
            ->middleware('throttle:platform.session');
    });

Route::prefix('api/v1/platform/password')
    ->middleware('throttle:platform.password-reset')
    ->group(function (): void {
        Route::post('/forgot', [PlatformPasswordResetController::class, 'forgotPassword'])
            ->name('platform.password.forgot');
        Route::post('/reset', [PlatformPasswordResetController::class, 'resetPassword'])
            ->name('platform.password.reset.submit');
    });

Route::post('/api/v1/platform/password/confirm', [PlatformPasswordConfirmationController::class, 'confirm'])
    ->middleware('throttle:platform.session');

Route::prefix('api/v1/platform/email')
    ->group(function (): void {
        Route::get('/verify/{id}/{hash}', [PlatformEmailVerificationController::class, 'verify'])
            ->middleware('signed')
            ->name('platform.email.verify');
        Route::post('/verification-notification', [PlatformEmailVerificationController::class, 'resend'])
            ->middleware('throttle:platform.session');
    });

Route::prefix(ApiVersion::COMMERCIAL_CATALOGUE_PREFIX)
    ->middleware('throttle:platform.admin')
    ->name('commercial-catalogue.')
    ->group(function (): void {
        Route::prefix('plans')
            ->name('plans.')
            ->group(function (): void {
                Route::get('/', [CommercialCataloguePlanController::class, 'index'])->name('index');
                Route::post('/', [CommercialCataloguePlanController::class, 'store'])->name('store');
                Route::get('/{planId}', [CommercialCataloguePlanController::class, 'show'])
                    ->whereUuid('planId')
                    ->name('show');
                Route::patch('/{planId}', [CommercialCataloguePlanController::class, 'update'])
                    ->whereUuid('planId')
                    ->name('update');
                Route::post('/{planId}/activate', [CommercialCataloguePlanController::class, 'activate'])
                    ->whereUuid('planId')
                    ->name('activate');
                Route::post('/{planId}/unavailable', [CommercialCataloguePlanController::class, 'unavailable'])
                    ->whereUuid('planId')
                    ->name('unavailable');
                Route::post('/{planId}/grandfather', [CommercialCataloguePlanController::class, 'grandfather'])
                    ->whereUuid('planId')
                    ->name('grandfather');
                Route::post('/{planId}/retire', [CommercialCataloguePlanController::class, 'retire'])
                    ->whereUuid('planId')
                    ->name('retire');
            });

        Route::prefix('billing-options')
            ->name('billing-options.')
            ->group(function (): void {
                Route::get('/', [CommercialCatalogueBillingOptionController::class, 'index'])->name('index');
                Route::post('/', [CommercialCatalogueBillingOptionController::class, 'store'])->name('store');
                Route::get('/{billingOptionId}', [CommercialCatalogueBillingOptionController::class, 'show'])
                    ->whereUuid('billingOptionId')
                    ->name('show');
                Route::patch('/{billingOptionId}', [CommercialCatalogueBillingOptionController::class, 'update'])
                    ->whereUuid('billingOptionId')
                    ->name('update');
            });

        Route::prefix('capabilities')
            ->name('capabilities.')
            ->group(function (): void {
                Route::get('/', [CommercialCatalogueCapabilityDefinitionController::class, 'index'])->name('index');
                Route::post('/', [CommercialCatalogueCapabilityDefinitionController::class, 'store'])->name('store');
                Route::get('/{capabilityId}', [CommercialCatalogueCapabilityDefinitionController::class, 'show'])
                    ->whereUuid('capabilityId')
                    ->name('show');
                Route::patch('/{capabilityId}', [CommercialCatalogueCapabilityDefinitionController::class, 'update'])
                    ->whereUuid('capabilityId')
                    ->name('update');
                Route::post('/{capabilityId}/activate', [CommercialCatalogueCapabilityDefinitionController::class, 'activate'])
                    ->whereUuid('capabilityId')
                    ->name('activate');
                Route::post('/{capabilityId}/deprecate', [CommercialCatalogueCapabilityDefinitionController::class, 'deprecate'])
                    ->whereUuid('capabilityId')
                    ->name('deprecate');
                Route::post('/{capabilityId}/retire', [CommercialCatalogueCapabilityDefinitionController::class, 'retire'])
                    ->whereUuid('capabilityId')
                    ->name('retire');
            });

        Route::prefix('plan-offerings')
            ->name('plan-offerings.')
            ->group(function (): void {
                Route::get('/', [CommercialCataloguePlanOfferingController::class, 'index'])->name('index');
                Route::post('/', [CommercialCataloguePlanOfferingController::class, 'store'])->name('store');
                Route::get('/{planOfferingId}', [CommercialCataloguePlanOfferingController::class, 'show'])
                    ->whereUuid('planOfferingId')
                    ->name('show');
                Route::patch('/{planOfferingId}', [CommercialCataloguePlanOfferingController::class, 'update'])
                    ->whereUuid('planOfferingId')
                    ->name('update');
                Route::post('/{planOfferingId}/activate', [CommercialCataloguePlanOfferingController::class, 'activate'])
                    ->whereUuid('planOfferingId')
                    ->name('activate');
                Route::post('/{planOfferingId}/unavailable', [CommercialCataloguePlanOfferingController::class, 'unavailable'])
                    ->whereUuid('planOfferingId')
                    ->name('unavailable');
                Route::post('/{planOfferingId}/grandfather', [CommercialCataloguePlanOfferingController::class, 'grandfather'])
                    ->whereUuid('planOfferingId')
                    ->name('grandfather');
                Route::post('/{planOfferingId}/retire', [CommercialCataloguePlanOfferingController::class, 'retire'])
                    ->whereUuid('planOfferingId')
                    ->name('retire');
            });
    });
