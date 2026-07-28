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
        'service_setup' => env('COMMERCIAL_SERVICE_SETUP_CAPABILITY_KEY', 'service_setup'),
    ],

    'trusted_consumers' => [
        'payment',
    ],
];
