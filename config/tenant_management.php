<?php

declare(strict_types=1);

return [
    'admin_base_domains' => explode(',', (string) env('TENANT_ADMIN_BASE_DOMAINS', 'app.syifa.my')),
    'session' => [
        'idle_minutes' => (int) env('SESSION_LIFETIME', 120),
        'absolute_lifetime_minutes' => (int) env('AUTH_SESSION_ABSOLUTE_LIFETIME', 720),
        'login_attempts_per_minute' => (int) env('AUTH_LOGIN_ATTEMPTS_PER_MINUTE', 10),
    ],
];
