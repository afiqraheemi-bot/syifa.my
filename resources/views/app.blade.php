<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @if (request()->routeIs('root'))
            @php
                $marketingTitle = 'SYIFA.my — Website & Sistem Tempahan Klinik';
                $marketingDescription = 'Website klinik profesional dengan sistem tempahan pesakit, templat premium dan onboarding terurus. Bina kehadiran digital klinik anda bersama SYIFA.my.';
                $marketingUrl = 'https://syifa.my/';
                $marketingImage = 'https://syifa.my/images/marketing/syifa-og.jpg';
                $marketingStructuredData = [
                    [
                        '@context' => 'https://schema.org',
                        '@type' => 'Organization',
                        '@id' => $marketingUrl.'#organization',
                        'name' => 'SYIFA.my',
                        'url' => $marketingUrl,
                        'logo' => 'https://syifa.my/images/marketing/syifa-logo.webp',
                        'image' => $marketingImage,
                        'contactPoint' => [[
                            '@type' => 'ContactPoint',
                            'telephone' => '+60134079388',
                            'contactType' => 'sales',
                            'areaServed' => 'MY',
                            'availableLanguage' => ['ms', 'en'],
                        ]],
                    ],
                    [
                        '@context' => 'https://schema.org',
                        '@type' => 'WebSite',
                        '@id' => $marketingUrl.'#website',
                        'url' => $marketingUrl,
                        'name' => 'SYIFA.my',
                        'description' => $marketingDescription,
                        'inLanguage' => 'ms-MY',
                        'publisher' => ['@id' => $marketingUrl.'#organization'],
                    ],
                    [
                        '@context' => 'https://schema.org',
                        '@type' => 'SoftwareApplication',
                        'name' => 'SYIFA.my',
                        'applicationCategory' => 'BusinessApplication',
                        'operatingSystem' => 'Web',
                        'url' => $marketingUrl,
                        'description' => $marketingDescription,
                        'provider' => ['@id' => $marketingUrl.'#organization'],
                    ],
                ];
            @endphp
            <title>{{ $marketingTitle }}</title>
            <meta name="description" content="{{ $marketingDescription }}">
            <meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">
            <meta name="theme-color" content="#047857">
            <link rel="canonical" href="{{ $marketingUrl }}">
            <link rel="icon" href="/favicon.svg" type="image/svg+xml">
            <meta property="og:type" content="website">
            <meta property="og:site_name" content="SYIFA.my">
            <meta property="og:locale" content="ms_MY">
            <meta property="og:title" content="{{ $marketingTitle }}">
            <meta property="og:description" content="{{ $marketingDescription }}">
            <meta property="og:url" content="{{ $marketingUrl }}">
            <meta property="og:image" content="{{ $marketingImage }}">
            <meta property="og:image:secure_url" content="{{ $marketingImage }}">
            <meta property="og:image:type" content="image/jpeg">
            <meta property="og:image:width" content="1200">
            <meta property="og:image:height" content="630">
            <meta property="og:image:alt" content="SYIFA.my — website klinik profesional dan sistem tempahan pesakit">
            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:title" content="{{ $marketingTitle }}">
            <meta name="twitter:description" content="{{ $marketingDescription }}">
            <meta name="twitter:image" content="{{ $marketingImage }}">
            <meta name="twitter:image:alt" content="SYIFA.my — website klinik profesional dan sistem tempahan pesakit">
            <script type="application/ld+json">{!! json_encode($marketingStructuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
        @else
            <meta name="robots" content="noindex,nofollow,noarchive">
        @endif

        @vite('resources/js/app.js')
        @inertiaHead
    </head>
    <body style="overflow-x: hidden;">
        @inertia
    </body>
</html>
