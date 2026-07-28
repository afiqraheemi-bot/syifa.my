<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Production Environment Guard
    |--------------------------------------------------------------------------
    |
    | These settings define centralized production-safety validation. The guard
    | validates only protected environments and reports violation codes without
    | exposing sensitive configuration values.
    |
    */

    'enabled' => env('PRODUCTION_GUARD_ENABLED', true),

    'validate_console' => env('PRODUCTION_GUARD_VALIDATE_CONSOLE', false),

    'protected_environments' => [
        'production',
    ],

    'required_config' => [
        'app.key',
        'app.url',
    ],

    'expected_values' => [
        'app.debug' => false,
        'edge_security.enabled' => true,
        'edge_security.trusted_hosts.enabled' => true,
        'http_security.enabled' => true,
        'filesystems.disks.local.serve' => false,
        'cache.default' => 'redis',
        'queue.default' => 'redis',
        'queue.connections.redis.after_commit' => true,
        'session.driver' => 'redis',
        'session.encrypt' => true,
        'session.http_only' => true,
        'session.secure' => true,
        'session.same_site' => 'lax',
    ],

    'required_url_schemes' => [
        'app.url' => 'https',
    ],
];
