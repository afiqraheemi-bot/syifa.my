<?php

declare(strict_types=1);

use App\Http\Controllers\OperationsController;
use App\Modules\Booking\Presentation\Http\Controllers\ClinicOwnerBookingOperationController;
use App\Modules\PlatformAdministration\Presentation\Http\Controllers\PlatformEmailVerificationController;
use App\Modules\PlatformAdministration\Presentation\Http\Controllers\PlatformPasswordConfirmationController;
use App\Modules\PlatformAdministration\Presentation\Http\Controllers\PlatformPasswordResetController;
use App\Modules\PlatformAdministration\Presentation\Http\Controllers\PlatformSessionController;
use App\Modules\PlatformAdministration\Presentation\Http\Middleware\AuthenticatePlatformSessionMiddleware;
use App\Modules\SubscriptionBilling\Presentation\ApiVersion;
use App\Modules\SubscriptionBilling\Presentation\Http\Controllers\CommercialCatalogueBillingOptionController;
use App\Modules\SubscriptionBilling\Presentation\Http\Controllers\CommercialCatalogueCapabilityDefinitionController;
use App\Modules\SubscriptionBilling\Presentation\Http\Controllers\CommercialCataloguePlanController;
use App\Modules\SubscriptionBilling\Presentation\Http\Controllers\CommercialCataloguePlanOfferingController;
use App\Modules\TenantManagement\Presentation\Http\Controllers\ClinicOwnerSessionController;
use App\Modules\TenantManagement\Presentation\Http\Middleware\AuthenticateClinicOwnerSessionMiddleware;
use App\Modules\WebsiteBuilder\Presentation\Http\Controllers\AvailabilityController;
use App\Modules\WebsiteBuilder\Presentation\Http\Controllers\BookingController;
use App\Modules\WebsiteBuilder\Presentation\Http\Controllers\PublicLegalDocumentController;
use App\Modules\WebsiteBuilder\Presentation\Http\Controllers\PublicWebsiteController;
use App\Modules\WebsiteBuilder\Presentation\Http\Controllers\SuccessController;
use App\Support\Dashboard\Presentation\Http\Controllers\AuthenticatedDashboardController;
use App\Support\Dashboard\Presentation\Http\Controllers\ClinicOwnerBookingOverviewController;
use App\Support\Dashboard\Presentation\Http\Controllers\ClinicOwnerWebsiteContentOverviewController;
use App\Support\Dashboard\Presentation\Http\Controllers\ClinicOwnerWebsiteOverviewController;
use App\Support\Dashboard\Presentation\Http\Controllers\WebsiteDesignerJobDetailController;
use App\Support\Dashboard\Presentation\Http\Controllers\WebsiteDesignerOnboardingQueueController;
use Illuminate\Support\Facades\Route;

Route::get('/', PublicWebsiteController::class)->name('public-website.home');
Route::get('/privacy', [PublicLegalDocumentController::class, 'privacy'])->name('public-website.privacy');
Route::get('/terms', [PublicLegalDocumentController::class, 'terms'])->name('public-website.terms');
Route::get('/dashboard', AuthenticatedDashboardController::class)
    ->middleware('authorize.context:authenticated,clinic_owner,website_designer,super_admin')
    ->name('dashboard');
Route::get('/dashboard/onboarding', WebsiteDesignerOnboardingQueueController::class)
    ->middleware('authorize.context:platform_identity,website_designer')
    ->name('dashboard.onboarding');
Route::get('/dashboard/onboarding/{jobId}', WebsiteDesignerJobDetailController::class)
    ->whereUuid('jobId')
    ->middleware('authorize.context:platform_identity,website_designer')
    ->name('dashboard.onboarding.show');
Route::get('/dashboard/website', ClinicOwnerWebsiteOverviewController::class)
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('dashboard.website');
Route::get('/dashboard/website/content', ClinicOwnerWebsiteContentOverviewController::class)
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('dashboard.website.content');
Route::get('/dashboard/bookings', ClinicOwnerBookingOverviewController::class)
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('dashboard.bookings');
Route::prefix('/dashboard/bookings/{bookingId}')
    ->whereUuid('bookingId')
    ->middleware('authorize.context:clinic_owner,clinic_owner')
    ->name('dashboard.bookings.')
    ->group(function (): void {
        Route::post('/confirm', [ClinicOwnerBookingOperationController::class, 'confirm'])->name('confirm');
        Route::post('/cancel', [ClinicOwnerBookingOperationController::class, 'cancel'])->name('cancel');
        Route::patch('/reschedule', [ClinicOwnerBookingOperationController::class, 'reschedule'])->name('reschedule');
    });

// Public Booking Delivery (ADR-029, amended by ADR-030/031) — finite, additive
// route set. No wildcard, no reference/bookingId-shaped parameter anywhere.
Route::prefix('booking')->name('public-website.booking.')->group(function (): void {
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
        });
}

Route::prefix('api/v1')
    ->group(function (): void {
        Route::post('/sessions', [ClinicOwnerSessionController::class, 'store'])
            ->middleware('throttle:clinic-owner-session');
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
            ->middleware('throttle:platform.login');
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
        Route::post('/forgot', [PlatformPasswordResetController::class, 'forgotPassword']);
        Route::post('/reset', [PlatformPasswordResetController::class, 'resetPassword']);
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
