<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Production Operations Foundation
    |--------------------------------------------------------------------------
    |
    | These settings define the lightweight operations surface exposed by the
    | application. Responses must stay deterministic and safe for production.
    |
    */

    'enabled' => env('OPERATIONS_ENDPOINTS_ENABLED', true),

    'prefix' => 'operations',

    'endpoints' => [
        'health' => 'health',
        'ready' => 'ready',
        'live' => 'live',
        'info' => 'info',
        'build' => 'build',
        'version' => 'version',
        'release' => 'release',
    ],

    'application' => [
        'service' => 'syifa.my',
        'component' => 'modular-monolith',
        'api_version' => 'v1',
    ],

    'release' => [
        'version' => env('RELEASE_VERSION', 'unknown'),
        'build_id' => env('RELEASE_BUILD_ID', 'unknown'),
        'commit' => env('RELEASE_COMMIT', 'unknown'),
        'built_at' => env('RELEASE_BUILT_AT', 'unknown'),
    ],

    'runtime_checks' => [
        'enabled' => env(
            'OPERATIONS_RUNTIME_CHECKS_ENABLED',
            env('APP_ENV', 'production') !== 'testing',
        ),
        'redis_connection' => env('OPERATIONS_REDIS_CONNECTION', 'default'),
    ],

    'checks' => [
        'health' => [],
        'readiness' => [],
        'liveness' => [],
    ],
];
