<?php

declare(strict_types=1);

use App\Modules\Commercial\Presentation\Http\Controllers\CommercialOfferController;
use App\Modules\PlatformAdministration\Presentation\Http\Middleware\AuthenticatePlatformSessionMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/commercial')
    ->name('commercial.')
    ->middleware(['throttle:platform.session', AuthenticatePlatformSessionMiddleware::class])
    ->group(function (): void {
        Route::get('/available-offers', [CommercialOfferController::class, 'availableOffers'])->name('available-offers');
        Route::post('/offers', [CommercialOfferController::class, 'store'])->name('offers.store');
        Route::get('/offers/current', [CommercialOfferController::class, 'current'])->name('offers.current');
        Route::get('/offers/{offerId}', [CommercialOfferController::class, 'show'])
            ->whereUuid('offerId')
            ->name('offers.show');
        Route::post('/offers/{offerId}/cancel', [CommercialOfferController::class, 'cancel'])
            ->whereUuid('offerId')
            ->name('offers.cancel');
    });
