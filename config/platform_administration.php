<?php

declare(strict_types=1);

return [
    'session' => [
        'idle_minutes' => (int) env('PLATFORM_SESSION_LIFETIME', 120),
        'absolute_lifetime_minutes' => (int) env('PLATFORM_AUTH_SESSION_ABSOLUTE_LIFETIME', 720),
    ],
    'mfa' => [
        'enabled' => (bool) env('PLATFORM_MFA_ENABLED', false),
    ],
    'local_demo_mfa' => [
        'enabled' => env('APP_ENV') === 'local',
        'code' => '123456',
        'platform_identity_ids' => [
            '00000000-0000-4000-8000-100000000001',
            '00000000-0000-4000-8000-100000000002',
        ],
    ],
];
