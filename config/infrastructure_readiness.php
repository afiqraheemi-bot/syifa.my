<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Infrastructure Readiness Foundation
    |--------------------------------------------------------------------------
    |
    | These settings define the deployment capability checks required before
    | the application should be considered ready to receive production traffic.
    | Checks are configuration-only and must not perform provider diagnostics.
    |
    */

    'enabled' => env('INFRASTRUCTURE_READINESS_ENABLED', true),

    'capabilities' => [
        'cache' => [
            'required' => true,
            'default_config_key' => 'cache.default',
            'configured_options_key' => 'cache.stores',
        ],

        'queue' => [
            'required' => true,
            'default_config_key' => 'queue.default',
            'configured_options_key' => 'queue.connections',
        ],

        'session' => [
            'required' => true,
            'default_config_key' => 'session.driver',
        ],

        'logging' => [
            'required' => true,
            'default_config_key' => 'logging.default',
            'configured_options_key' => 'logging.channels',
        ],

        'filesystem' => [
            'required' => true,
            'default_config_key' => 'filesystems.default',
            'configured_options_key' => 'filesystems.disks',
        ],
    ],
];
