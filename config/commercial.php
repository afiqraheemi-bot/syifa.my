<?php

declare(strict_types=1);

return [
    'enabled' => env('COMMERCIAL_MODULE_ENABLED', true),

    'routes' => [
        'enabled' => env('COMMERCIAL_ROUTES_ENABLED', true),
    ],

    'offer_ttl_minutes' => 30,

    'capabilities' => [
        'custom_domain' => env('COMMERCIAL_CUSTOM_DOMAIN_CAPABILITY_KEY', 'custom_domain'),
    ],

    'trusted_consumers' => [
        'payment',
    ],
];
