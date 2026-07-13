<?php

declare(strict_types=1);

use App\Modules\TenantManagement\Presentation\Http\Controllers\ClinicOwnerSessionController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->group(function (): void {
        Route::post('/sessions', [ClinicOwnerSessionController::class, 'store'])
            ->middleware('throttle:clinic-owner-session');
        Route::get('/sessions/current', [ClinicOwnerSessionController::class, 'show']);
        Route::delete('/sessions/current', [ClinicOwnerSessionController::class, 'destroy']);
    });
