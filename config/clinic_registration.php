<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Clinic Registration Foundation
    |--------------------------------------------------------------------------
    |
    | This configuration reserves centralized framework settings for the
    | Clinic Registration module. The module is discoverable but intentionally
    | exposes no registration workflow, endpoint, persistence, or domain model.
    |
    */

    'enabled' => env('CLINIC_REGISTRATION_ENABLED', true),

    'routes' => [
        'enabled' => env('CLINIC_REGISTRATION_ROUTES_ENABLED', true),
    ],
];
