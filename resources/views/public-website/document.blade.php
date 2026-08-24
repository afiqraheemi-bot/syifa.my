<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @php
        // og:image has no dashboard field to set explicitly today, so fall
        // back to the clinic's own visuals rather than emitting no image at
        // all - the hero photo makes the best share-card image; the logo is
        // the next best thing when there is no hero section.
        $ogImageAssetId = $document->website->seo->openGraphImageAssetId;
        if ($ogImageAssetId === null) {
            foreach ($document->website->sections as $ogFallbackSection) {
                if ($ogFallbackSection->type() === 'HERO' && $ogFallbackSection->heroImageAssetId !== null) {
                    $ogImageAssetId = $ogFallbackSection->heroImageAssetId;
                    break;
                }
            }
        }
        if ($ogImageAssetId === null) {
            $ogImageAssetId = $document->website->header->logoAssetId;
        }
        $ogImageUrl = $ogImageAssetId === null ? null : ($document->assetUrls[$ogImageAssetId] ?? null);
    @endphp
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
    @if ($ogImageUrl !== null)
        <meta property="og:image" content="{{ $ogImageUrl->value }}">
        <meta property="og:image:alt" content="{{ $document->website->branding->clinicName }}">
    @endif
    <meta name="twitter:card" content="{{ $ogImageUrl !== null ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $document->head->openGraphTitle }}">
    <meta name="twitter:description" content="{{ $document->head->openGraphDescription }}">
    @if ($document->website->branding->faviconAssetId !== null && isset($document->assetUrls[$document->website->branding->faviconAssetId]))
        <link rel="icon" href="{{ $document->assetUrls[$document->website->branding->faviconAssetId]->value }}">
    @endif
    <script type="application/ld+json">{!! $document->head->jsonLd() !!}</script>
    @unless (app()->environment('testing')) @vite(['resources/css/public-website.css', 'resources/js/public-website.js', 'resources/js/public-content-enhancements.js', 'resources/js/blog-slider.js']) @endunless
    <style>:root{--brand-primary:{{ $document->brandTokens->primary }};--brand-primary-hover:{{ $document->brandTokens->primaryHover }};--brand-primary-active:{{ $document->brandTokens->primaryActive }};--brand-on-primary:{{ $document->brandTokens->onPrimary }};--brand-secondary:{{ $document->brandTokens->secondary }};--brand-on-secondary:{{ $document->brandTokens->onSecondary }};}</style>
</head>
<body
    class="public-site"
    data-template="{{ strtolower(str_replace('_', '-', $document->website->website->templateId)) }}"
>
    <x-public.skip-link />
    <x-public.navbar :document="$document" :blog-enabled="$blogEnabled ?? false" blog-url="#blog" />

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
                    <x-public.services :section="$section" :booking-url="$document->bookingDestination" :template-id="$document->website->website->templateId" />
                    @break
                @case('DOCTORS')
                    <x-public.doctors :section="$section" :asset-urls="$document->assetUrls" :asset-dimensions="$document->assetDimensions" :template-id="$document->website->website->templateId" />
                    @break
                @case('TESTIMONIALS')
                    <x-public.testimonials :section="$section" :template-id="$document->website->website->templateId" />
                    @break
                @case('GALLERY')
                    <x-public.gallery :section="$section" :asset-urls="$document->assetUrls" :asset-dimensions="$document->assetDimensions" :template-id="$document->website->website->templateId" />
                    @break
                @case('FAQ')
                    <x-public.faq :section="$section" :template-id="$document->website->website->templateId" />
                    @break
                @case('CONTACT')
                    @break
                @case('BOOKING_CTA')
                    <x-public.booking-cta :section="$section" :booking-url="$document->bookingDestination" :phone-url="$document->contactActions->telephone" />
                    @break
            @endswitch
        @endforeach
        <x-public.blog-slider :articles="$latestBlogPosts ?? collect()" />
    </main>

    <x-public.footer :document="$document" :contact-section="$contactSection" />
    <x-public.whatsapp-button :action="$document->contactActions->whatsApp" :button-style="$document->website->branding->whatsAppButtonStyle" />
</body>
</html>
