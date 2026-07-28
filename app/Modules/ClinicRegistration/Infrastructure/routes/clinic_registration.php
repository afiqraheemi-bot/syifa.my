<?php

declare(strict_types=1);

use App\Modules\ClinicRegistration\Presentation\Http\Controllers\ClinicRegistrationController;
use App\Modules\ClinicRegistration\Presentation\Http\Controllers\PublicClinicRegistrationController;
use App\Modules\Commercial\Presentation\Http\Controllers\PublicCommercialOfferController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/clinic-registrations')
    ->name('clinic-registration.')
    ->middleware(['web', 'throttle:public.default'])
    ->group(function (): void {
        Route::post('/', [ClinicRegistrationController::class, 'store'])->name('store');
        Route::get('/current', [ClinicRegistrationController::class, 'show'])->name('current.show');
        Route::patch('/current', [ClinicRegistrationController::class, 'update'])->name('current.update');
        Route::post('/current/submit', [ClinicRegistrationController::class, 'submit'])->name('current.submit');
        Route::post('/current/cancel', [ClinicRegistrationController::class, 'cancel'])->name('current.cancel');
    });

Route::get('/register', PublicClinicRegistrationController::class)
    ->middleware(['web', 'throttle:public.default'])
    ->name('clinic-registration.browser');

Route::get('/register/offers', [PublicCommercialOfferController::class, 'index'])
    ->middleware(['web', 'throttle:public.default'])
    ->name('clinic-registration.offers');

Route::post('/register/offers/selection', [PublicCommercialOfferController::class, 'select'])
    ->middleware(['web', 'throttle:public.default'])
    ->name('clinic-registration.offers.select');
