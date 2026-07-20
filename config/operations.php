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
    ],

    'application' => [
        'service' => 'syifa.my',
        'component' => 'modular-monolith',
        'api_version' => 'v1',
    ],

    'checks' => [
        'health' => [],
        'readiness' => [],
        'liveness' => [],
    ],
];
