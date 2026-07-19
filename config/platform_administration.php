<?php

declare(strict_types=1);

return [
    'session' => [
        'idle_minutes' => (int) env('PLATFORM_SESSION_LIFETIME', 120),
        'absolute_lifetime_minutes' => (int) env('PLATFORM_AUTH_SESSION_ABSOLUTE_LIFETIME', 720),
    ],
];
