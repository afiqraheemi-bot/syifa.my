<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document->head->title }}</title>
    <meta name="description" content="{{ $document->head->description }}">
    <meta name="robots" content="{{ $document->head->robots }}">
    <meta name="theme-color" content="{{ $document->brandTokens->primary }}">
    <link rel="canonical" href="{{ $document->head->canonicalUrl->value }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $document->website->branding->clinicName }}">
    <meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale()) }}">
    <meta property="og:title" content="{{ $document->head->openGraphTitle }}">
    <meta property="og:description" content="{{ $document->head->openGraphDescription }}">
    <meta property="og:url" content="{{ $document->head->openGraphUrl->value }}">
    @if ($document->website->seo->openGraphImageAssetId !== null && isset($document->assetUrls[$document->website->seo->openGraphImageAssetId]))
        <meta property="og:image" content="{{ $document->assetUrls[$document->website->seo->openGraphImageAssetId]->value }}">
        <meta property="og:image:alt" content="{{ $document->website->branding->clinicName }}">
    @endif
    <meta name="twitter:card" content="{{ $document->website->seo->openGraphImageAssetId !== null ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $document->head->openGraphTitle }}">
    <meta name="twitter:description" content="{{ $document->head->openGraphDescription }}">
    @if ($document->website->branding->faviconAssetId !== null && isset($document->assetUrls[$document->website->branding->faviconAssetId]))
        <link rel="icon" href="{{ $document->assetUrls[$document->website->branding->faviconAssetId]->value }}">
    @endif
    <script type="application/ld+json">{!! $document->head->jsonLd() !!}</script>
    @unless (app()->environment('testing')) @vite(['resources/css/public-website.css', 'resources/js/public-website.js']) @endunless
    <style>:root{--brand-primary:{{ $document->brandTokens->primary }};--brand-primary-hover:{{ $document->brandTokens->primaryHover }};--brand-primary-active:{{ $document->brandTokens->primaryActive }};--brand-on-primary:{{ $document->brandTokens->onPrimary }};--brand-secondary:{{ $document->brandTokens->secondary }};--brand-on-secondary:{{ $document->brandTokens->onSecondary }};}</style>
</head>
<body
    class="public-site"
    data-template="{{ strtolower(str_replace('_', '-', $document->website->website->templateId)) }}"
>
    <x-public.skip-link />
    <x-public.navbar :document="$document" />

    @php
        $contactSection = null;

        foreach ($document->website->sections as $renderedSection) {
            if ($renderedSection->type() === 'CONTACT') {
                $contactSection = $renderedSection;
            }
        }
    @endphp
    <main id="main-content">
        @foreach ($document->website->sections as $section)
            @switch($section->type())
                @case('HERO')
                    <x-public.hero :section="$section" :document="$document" />
                    @break
                @case('ABOUT')
                    <x-public.about :section="$section" :asset-urls="$document->assetUrls" :asset-dimensions="$document->assetDimensions" />
                    @break
                @case('SERVICES')
                    <x-public.services :section="$section" :booking-url="$document->bookingDestination" />
                    @break
                @case('DOCTORS')
                    <x-public.doctors :section="$section" :asset-urls="$document->assetUrls" :asset-dimensions="$document->assetDimensions" />
                    @break
                @case('TESTIMONIALS')
                    <x-public.testimonials :section="$section" />
                    @break
                @case('GALLERY')
                    <x-public.gallery :section="$section" :asset-urls="$document->assetUrls" :asset-dimensions="$document->assetDimensions" />
                    @break
                @case('FAQ')
                    <x-public.faq :section="$section" />
                    @break
                @case('CONTACT')
                    @break
                @case('BOOKING_CTA')
                    <x-public.booking-cta :section="$section" :booking-url="$document->bookingDestination" :phone-url="$document->contactActions->telephone" />
                    @break
            @endswitch
        @endforeach
    </main>

    <x-public.footer :document="$document" :contact-section="$contactSection" />
    <x-public.whatsapp-button :action="$document->contactActions->whatsApp" :button-style="$document->website->branding->whatsAppButtonStyle" />
</body>
</html>
