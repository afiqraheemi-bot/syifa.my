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

    'access' => [
        'remember_minutes' => (int) env('CLINIC_REGISTRATION_REMEMBER_MINUTES', 43200),
    ],

    'trusted_completion_sources' => [
        'tenant_management',
        'provisioning_orchestrator',
    ],

    'language' => [
        'terms' => [
            'clinic' => [
                'label' => 'Clinic',
                'definition' => 'A healthcare clinic that will be represented on Syifa.my after registration is completed by future workflows.',
            ],

            'clinic_owner' => [
                'label' => 'Clinic Owner',
                'definition' => 'The tenant-owned authority responsible for a registered clinic, distinct from platform workforce identities.',
            ],

            'clinic_registration' => [
                'label' => 'Clinic Registration',
                'definition' => 'The future process for introducing a clinic into Syifa.my before tenant onboarding and website setup proceed.',
            ],

            'registration_request' => [
                'label' => 'Registration Request',
                'definition' => 'A future request to start clinic registration, before any accepted workflow or persistence behavior is introduced.',
            ],

            'registration_status' => [
                'label' => 'Registration Status',
                'definition' => 'The future vocabulary for describing clinic registration progress without defining lifecycle rules in this foundation.',
            ],

            'subscription_selection' => [
                'label' => 'Subscription Selection',
                'definition' => 'The future choice of a commercial subscription offering during clinic registration, without computing entitlement here.',
            ],

            'add_on_selection' => [
                'label' => 'Add-On Selection',
                'definition' => 'A deferred future commercial selection term; Add-On behavior remains outside the active Phase 1 implementation.',
            ],

            'onboarding' => [
                'label' => 'Onboarding',
                'definition' => 'The future handoff from accepted clinic registration into the existing onboarding bounded context vocabulary.',
            ],

            'website_setup' => [
                'label' => 'Website Setup',
                'definition' => 'The future preparation activity for a clinic website after registration, separate from Website Builder implementation.',
            ],

            'publish' => [
                'label' => 'Publish',
                'definition' => 'The future act of making a prepared clinic website publicly available, without implementing website publishing here.',
            ],
        ],
    ],
];
