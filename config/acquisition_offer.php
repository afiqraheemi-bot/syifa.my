<?php

declare(strict_types=1);

return [
    'enabled' => env('ACQUISITION_OFFER_MODULE_ENABLED', true),

    'routes' => [
        'enabled' => env('ACQUISITION_OFFER_ROUTES_ENABLED', true),
    ],

    'offer_ttl_minutes' => 30,

    'capabilities' => [
        'custom_domain' => env('ACQUISITION_OFFER_CUSTOM_DOMAIN_CAPABILITY_KEY', 'custom_domain'),
        'service_setup' => env('ACQUISITION_OFFER_SERVICE_SETUP_CAPABILITY_KEY', 'booking.manage'),
    ],

    'trusted_consumers' => [
        'payment',
    ],
];
