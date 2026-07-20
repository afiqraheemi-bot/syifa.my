<?php

declare(strict_types=1);

use Illuminate\Http\Request;

return [
    /*
    |--------------------------------------------------------------------------
    | Platform Edge Security
    |--------------------------------------------------------------------------
    |
    | These settings describe the trusted HTTP edge in front of the
    | application. They are intentionally provider-neutral: deployment
    | environments supply their own proxy and host values.
    |
    */

    'enabled' => env('EDGE_SECURITY_ENABLED', true),

    'trusted_proxies' => [
        'proxies' => env('EDGE_TRUSTED_PROXIES', ''),
        'headers' => Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_PREFIX
            | Request::HEADER_X_FORWARDED_AWS_ELB,
    ],

    'trusted_hosts' => [
        'enabled' => env('APP_ENV', 'production') === 'production',
        'include_app_url_host' => true,
        'include_app_url_subdomains' => true,
        'hosts' => env('EDGE_TRUSTED_HOSTS', ''),
    ],

    'secure_url_generation' => [
        'enabled' => true,
    ],
];
