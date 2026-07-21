<?php

declare(strict_types=1);

return [
    'enabled' => env('COMMERCIAL_MODULE_ENABLED', true),

    'routes' => [
        'enabled' => env('COMMERCIAL_ROUTES_ENABLED', true),
    ],

    'offer_ttl_minutes' => 30,

    'trusted_consumers' => [
        'payment',
    ],
];
