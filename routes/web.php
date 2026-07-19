<?php

declare(strict_types=1);

use App\Modules\PlatformAdministration\Presentation\Http\Controllers\PlatformSessionController;
use App\Modules\PlatformAdministration\Presentation\Http\Middleware\AuthenticatePlatformSessionMiddleware;
use App\Modules\TenantManagement\Presentation\Http\Controllers\ClinicOwnerSessionController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->group(function (): void {
        Route::post('/sessions', [ClinicOwnerSessionController::class, 'store'])
            ->middleware('throttle:clinic-owner-session');
        Route::get('/sessions/current', [ClinicOwnerSessionController::class, 'show']);
        Route::delete('/sessions/current', [ClinicOwnerSessionController::class, 'destroy']);
    });

Route::prefix('api/v1/platform/sessions')
    ->group(function (): void {
        Route::post('/', [PlatformSessionController::class, 'store']);
        Route::get('/current', [PlatformSessionController::class, 'show'])
            ->middleware(AuthenticatePlatformSessionMiddleware::class);
        Route::delete('/current', [PlatformSessionController::class, 'destroy']);
    });
