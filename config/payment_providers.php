<?php

declare(strict_types=1);

$applicationUrl = rtrim((string) config('app.url'), '/');

return [
    'verification' => [
        'queue_connection' => env('PAYMENT_VERIFICATION_QUEUE_CONNECTION', 'redis'),
        'queue_name' => env('PAYMENT_VERIFICATION_QUEUE', 'payment-verification'),
        'lease_seconds' => (int) env('PAYMENT_VERIFICATION_LEASE_SECONDS', 300),
        'transport_max_attempts' => (int) env('PAYMENT_VERIFICATION_TRANSPORT_MAX_ATTEMPTS', 8),
        'malformed_max_attempts' => (int) env('PAYMENT_VERIFICATION_MALFORMED_MAX_ATTEMPTS', 2),
        'base_delay_seconds' => (int) env('PAYMENT_VERIFICATION_BASE_DELAY_SECONDS', 30),
        'max_delay_seconds' => (int) env('PAYMENT_VERIFICATION_MAX_DELAY_SECONDS', 1800),
        'max_retry_after_seconds' => (int) env('PAYMENT_VERIFICATION_MAX_RETRY_AFTER_SECONDS', 21600),
    ],
    'application' => [
        'lease_seconds' => (int) env('PAYMENT_APPLICATION_LEASE_SECONDS', 120),
        'max_attempts' => (int) env('PAYMENT_APPLICATION_MAX_ATTEMPTS', 5),
        'base_delay_seconds' => (int) env('PAYMENT_APPLICATION_BASE_DELAY_SECONDS', 5),
        'max_delay_seconds' => (int) env('PAYMENT_APPLICATION_MAX_DELAY_SECONDS', 120),
    ],
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
