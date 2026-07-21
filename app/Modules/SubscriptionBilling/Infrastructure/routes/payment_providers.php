<?php

declare(strict_types=1);

use App\Modules\PlatformAdministration\Presentation\Http\Middleware\AuthenticatePlatformSessionMiddleware;
use App\Modules\SubscriptionBilling\Presentation\Http\Controllers\PaymentProviderAdministrationController;
use App\Modules\SubscriptionBilling\Presentation\Http\Controllers\PaymentProviderWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/platform/payment-providers')
    ->name('payment-providers.')
    ->middleware(['throttle:platform.admin', AuthenticatePlatformSessionMiddleware::class])
    ->group(function (): void {
        Route::get('/', [PaymentProviderAdministrationController::class, 'index'])->name('index');
        Route::post('/{providerKey}/assess', [PaymentProviderAdministrationController::class, 'assess'])->name('assess');
        Route::post('/{providerKey}/enable', [PaymentProviderAdministrationController::class, 'enable'])->name('enable');
        Route::post('/{providerKey}/disable', [PaymentProviderAdministrationController::class, 'disable'])->name('disable');
        Route::post('/{providerKey}/default', [PaymentProviderAdministrationController::class, 'makeDefault'])->name('default');
    });

Route::post('api/v1/payment-provider-webhooks/{providerKey}', [PaymentProviderWebhookController::class, 'receive'])
    ->where('providerKey', '[a-z][a-z0-9_-]{1,39}')
    ->middleware('throttle:payment-provider-webhook')
    ->name('payment-provider-webhooks.receive');
