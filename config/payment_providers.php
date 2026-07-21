<?php

declare(strict_types=1);

$applicationUrl = rtrim((string) config('app.url'), '/');

return [
    'stripe' => [
        'secret_key' => env('STRIPE_SECRET_KEY', ''),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
        'success_url' => env('STRIPE_SUCCESS_URL', env('APP_URL').'/payments/return'),
        'cancel_url' => env('STRIPE_CANCEL_URL', env('APP_URL').'/payments/cancel'),
        'base_url' => env('STRIPE_API_BASE_URL', 'https://api.stripe.com/v1'),
    ],
    'toyyibpay' => [
        'secret_key' => env('TOYYIBPAY_SECRET_KEY', ''),
        'category_code' => env('TOYYIBPAY_CATEGORY_CODE', ''),
        'return_url' => env('TOYYIBPAY_RETURN_URL', env('APP_URL').'/payments/return'),
        'callback_url' => env('TOYYIBPAY_CALLBACK_URL', $applicationUrl.'/api/v1/payment-provider-webhooks/toyyibpay'),
        'base_url' => env('TOYYIBPAY_BASE_URL', 'https://toyyibpay.com'),
    ],
];
