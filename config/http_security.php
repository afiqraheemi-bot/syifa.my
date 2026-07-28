<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | HTTP Security Headers
    |--------------------------------------------------------------------------
    |
    | These values define the platform-wide HTTP security header policy. The
    | middleware applies them globally while keeping local development usable
    | for Vite and browser tooling.
    |
    */

    'enabled' => env('HTTP_SECURITY_HEADERS_ENABLED', true),

    'environment' => env('APP_ENV', 'production') === 'production'
        ? 'production'
        : 'development',

    'content_security_policy' => [
        'production' => implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "connect-src 'self'",
            "font-src 'self' data:",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "img-src 'self' data:",
            "object-src 'none'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline'",
            'upgrade-insecure-requests',
        ]),
        'development' => implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "connect-src 'self' ws: wss: http://localhost:* http://127.0.0.1:*",
            "font-src 'self' data:",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "img-src 'self' data:",
            "object-src 'none'",
            "script-src 'self' 'unsafe-inline' http://localhost:* http://127.0.0.1:*",
            "style-src 'self' 'unsafe-inline'",
            "worker-src 'self' blob:",
        ]),
    ],

    'strict_transport_security' => [
        'enabled' => env('APP_ENV', 'production') === 'production',
        'max_age' => 31536000,
        'include_subdomains' => true,
        'preload' => false,
    ],

    'x_frame_options' => 'DENY',

    'x_content_type_options' => 'nosniff',

    'x_permitted_cross_domain_policies' => 'none',

    'referrer_policy' => 'strict-origin-when-cross-origin',

    'cross_origin_opener_policy' => 'same-origin',

    'cross_origin_resource_policy' => 'same-origin',

    'origin_agent_cluster' => '?1',

    'permissions_policy' => implode(', ', [
        'accelerometer=()',
        'autoplay=()',
        'camera=()',
        'encrypted-media=()',
        'fullscreen=(self)',
        'geolocation=()',
        'gyroscope=()',
        'magnetometer=()',
        'microphone=()',
        'midi=()',
        'payment=()',
        'usb=()',
    ]),
];
