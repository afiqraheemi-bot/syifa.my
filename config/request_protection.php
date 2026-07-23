<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Request Protection Profiles
    |--------------------------------------------------------------------------
    |
    | Named limiter profiles centralize transport-level abuse protection for
    | authentication, administration, session, and public surfaces. Routes
    | should reference only these names and never carry numeric limits.
    |
    */

    'profiles' => [
        'platform_login' => [
            'limiter' => 'platform.login',
            'max_attempts' => (int) env('REQUEST_PROTECTION_PLATFORM_LOGIN_MAX_ATTEMPTS', 5),
            'decay_seconds' => (int) env('REQUEST_PROTECTION_PLATFORM_LOGIN_DECAY_SECONDS', 60),
            'type' => 'authentication_temporarily_unavailable',
            'title' => 'Authentication Temporarily Unavailable',
            'detail' => 'Authentication is temporarily unavailable. Please try again later.',
            'key_parts' => ['host', 'email', 'network'],
        ],

        'platform_session' => [
            'limiter' => 'platform.session',
            'max_attempts' => (int) env('REQUEST_PROTECTION_PLATFORM_SESSION_MAX_ATTEMPTS', 120),
            'decay_seconds' => (int) env('REQUEST_PROTECTION_PLATFORM_SESSION_DECAY_SECONDS', 60),
            'type' => 'session_temporarily_unavailable',
            'title' => 'Session Temporarily Unavailable',
            'detail' => 'Session access is temporarily unavailable. Please try again later.',
            'key_parts' => ['host', 'actor', 'session', 'network'],
        ],

        'platform_administration' => [
            'limiter' => 'platform.admin',
            'max_attempts' => (int) env('REQUEST_PROTECTION_PLATFORM_ADMINISTRATION_MAX_ATTEMPTS', 240),
            'decay_seconds' => (int) env('REQUEST_PROTECTION_PLATFORM_ADMINISTRATION_DECAY_SECONDS', 60),
            'type' => 'administration_temporarily_unavailable',
            'title' => 'Administration Temporarily Unavailable',
            'detail' => 'Administrative access is temporarily unavailable. Please try again later.',
            'key_parts' => ['host', 'tenant', 'actor', 'session', 'network'],
        ],

        'public' => [
            'limiter' => 'public.default',
            'max_attempts' => (int) env('REQUEST_PROTECTION_PUBLIC_MAX_ATTEMPTS', 120),
            'decay_seconds' => (int) env('REQUEST_PROTECTION_PUBLIC_DECAY_SECONDS', 60),
            'type' => 'public_access_temporarily_unavailable',
            'title' => 'Public Access Temporarily Unavailable',
            'detail' => 'Public access is temporarily unavailable. Please try again later.',
            'key_parts' => ['host', 'tenant', 'network'],
        ],

        'clinic_owner_session' => [
            'limiter' => 'clinic-owner-session',
            'max_attempts' => (int) env('AUTH_LOGIN_ATTEMPTS_PER_MINUTE', 10),
            'decay_seconds' => 60,
            'type' => 'authentication_temporarily_unavailable',
            'title' => 'Authentication Temporarily Unavailable',
            'detail' => 'Authentication is temporarily unavailable. Please try again later.',
            'key_parts' => ['host', 'email', 'network'],
        ],

        'public_booking_submit' => [
            'limiter' => 'booking-submission',
            'max_attempts' => (int) env('REQUEST_PROTECTION_BOOKING_SUBMISSION_MAX_ATTEMPTS', 10),
            'decay_seconds' => (int) env('REQUEST_PROTECTION_BOOKING_SUBMISSION_DECAY_SECONDS', 60),
            'type' => 'booking_temporarily_unavailable',
            'title' => 'Booking Temporarily Unavailable',
            'detail' => 'Something went wrong on our end. Please try again.',
            'key_parts' => ['host', 'network'],
            // ADR-027's Security category: the public booking flow is an HTML
            // form, never a JSON API consumer — a visitor who trips this limit
            // must see the same generic, unremarkable Error Recovery screen as
            // every other failure category, never a raw problem+json body and
            // never any indication that abuse was suspected.
            'html_redirect_route' => 'public-website.booking.review',
            'html_error_key' => 'infrastructure',
        ],

        'payment_provider_webhook' => [
            'limiter' => 'payment-provider-webhook',
            'max_attempts' => (int) env('REQUEST_PROTECTION_PAYMENT_WEBHOOK_MAX_ATTEMPTS', 300),
            'decay_seconds' => (int) env('REQUEST_PROTECTION_PAYMENT_WEBHOOK_DECAY_SECONDS', 60),
            'type' => 'webhook_temporarily_unavailable',
            'title' => 'Webhook Temporarily Unavailable',
            'detail' => 'The webhook endpoint is temporarily unavailable. Please retry later.',
            'key_parts' => ['host', 'network'],
        ],
    ],
];
