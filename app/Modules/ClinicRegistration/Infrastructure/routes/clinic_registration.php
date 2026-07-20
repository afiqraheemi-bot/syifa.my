<?php

declare(strict_types=1);

use App\Modules\ClinicRegistration\Presentation\Http\Controllers\ClinicRegistrationController;
use App\Modules\PlatformAdministration\Presentation\Http\Middleware\AuthenticatePlatformSessionMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/clinic-registrations')
    ->name('clinic-registration.')
    ->middleware(['throttle:platform.session', AuthenticatePlatformSessionMiddleware::class])
    ->group(function (): void {
        Route::post('/', [ClinicRegistrationController::class, 'store'])->name('store');
        Route::get('/current', [ClinicRegistrationController::class, 'show'])->name('current.show');
        Route::patch('/current', [ClinicRegistrationController::class, 'update'])->name('current.update');
        Route::post('/current/submit', [ClinicRegistrationController::class, 'submit'])->name('current.submit');
        Route::post('/current/cancel', [ClinicRegistrationController::class, 'cancel'])->name('current.cancel');
    });
