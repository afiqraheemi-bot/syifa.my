<?php

declare(strict_types=1);

return [
    'asset_origin' => env('PUBLIC_WEBSITE_ASSET_ORIGIN', 'https://assets.syifa.my'),
    'base_domain' => strtolower((string) env('PUBLIC_WEBSITE_BASE_DOMAIN', 'syifa.my')),
    'runtime_addressing' => env('APP_ENV') !== 'testing'
        && (bool) env('PUBLIC_WEBSITE_RUNTIME_ADDRESSING', true),
    'custom_domain_targets' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PUBLIC_WEBSITE_CUSTOM_DOMAIN_TARGETS', '')),
    ))),

    // Local-development-only host-to-Website mapping. This can only ever be
    // non-empty when APP_ENV=local, regardless of what these env vars contain,
    // so it is never reachable outside a developer's own machine.
    'sites' => env('APP_ENV') === 'local' && env('PUBLIC_WEBSITE_PREVIEW_WEBSITE_ID') !== null
        ? [(string) env('PUBLIC_WEBSITE_PREVIEW_HOST', 'localhost') => [
            'website_id' => (string) env('PUBLIC_WEBSITE_PREVIEW_WEBSITE_ID'),
            'scheme' => (string) env('PUBLIC_WEBSITE_PREVIEW_SCHEME', 'http'),
        ]]
        : [],

    // Legal documents fail closed until approved, versioned copy is supplied
    // through an environment-specific configuration source.
    'legal' => [
        'privacy' => null,
        'terms' => null,
    ],
];
