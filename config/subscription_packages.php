<?php

declare(strict_types=1);

return [
    /*
     * This is the authoritative public catalogue and its display order.
     * Historical/demo plans may remain in persistence for audit purposes, but
     * they must never leak into the clinic registration journey.
     */
    'public_package_order' => [
        'package:syifa-trial',
        'package:syifa-basic',
        'package:syifa-standard',
    ],

    /*
     * These profiles are referenced by immutable commercial offerings. Keep
     * keys stable: existing subscriptions depend on their historical profile.
     */
    'capability_profiles' => [
        'package:syifa-trial' => [
            'website.managed',
            'website.content.manage',
            'website.branding.manage',
            'website.search_sharing.manage',
            'booking.online',
            'booking.manage',
            'booking.schedule.manage',
        ],
        'package:syifa-basic' => [
            'website.managed',
            'website.content.manage',
            'website.branding.manage',
            'website.search_sharing.manage',
            'booking.online',
            'booking.manage',
            'booking.schedule.manage',
        ],
        'package:syifa-standard' => [
            'website.managed',
            'website.content.manage',
            'website.branding.manage',
            'website.search_sharing.manage',
            'booking.online',
            'booking.manage',
            'booking.schedule.manage',
            'syifa_ai.assist',
            'custom_domain',
        ],
    ],
];
