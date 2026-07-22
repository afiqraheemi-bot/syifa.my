<?php

declare(strict_types=1);

return [
    'asset_origin' => env('PUBLIC_WEBSITE_ASSET_ORIGIN', 'https://assets.syifa.my'),

    // Local-development-only host-to-Website mapping. This can only ever be
    // non-empty when APP_ENV=local, regardless of what these env vars contain,
    // so it is never reachable outside a developer's own machine.
    'sites' => env('APP_ENV') === 'local' && env('PUBLIC_WEBSITE_PREVIEW_WEBSITE_ID') !== null
        ? [(string) env('PUBLIC_WEBSITE_PREVIEW_HOST', 'localhost') => [
            'website_id' => (string) env('PUBLIC_WEBSITE_PREVIEW_WEBSITE_ID'),
            'scheme' => (string) env('PUBLIC_WEBSITE_PREVIEW_SCHEME', 'http'),
        ]]
        : [],

    'legal' => [
        'privacy' => null,
        'terms' => null,
    ],
];
