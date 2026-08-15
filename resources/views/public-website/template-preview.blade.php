<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Template Preview · {{ $document->head->title }}</title>
    <meta name="description" content="{{ $document->head->description }}">
    <meta name="robots" content="noindex,nofollow,noarchive">
    @unless (app()->environment('testing')) @vite(['resources/css/public-website.css', 'resources/js/public-website.js']) @endunless
    <style>:root{--brand-primary:{{ $document->brandTokens->primary }};--brand-primary-hover:{{ $document->brandTokens->primaryHover }};--brand-primary-active:{{ $document->brandTokens->primaryActive }};--brand-on-primary:{{ $document->brandTokens->onPrimary }};--brand-secondary:{{ $document->brandTokens->secondary }};--brand-on-secondary:{{ $document->brandTokens->onSecondary }};}</style>
</head>
<body
    class="public-site"
    data-template="{{ strtolower(str_replace('_', '-', $document->website->website->templateId)) }}"
>
    @php
        $previewHeroImages = [
            'SYIFA_ESSENTIAL' => asset('images/template-previews/syifa-essential-hero.webp'),
            'SYIFA_CARE' => asset('images/template-previews/syifa-care-hero.webp'),
            'SYIFA_DENTAL' => asset('images/template-previews/syifa-dental-hero.webp'),
            'SYIFA_AESTHETIC' => asset('images/template-previews/syifa-aesthetic-hero.webp'),
            'SYIFA_SPECIALIST' => asset('images/template-previews/syifa-specialist-hero.webp'),
        ];
        $previewHeroImage = $previewHeroImageOverride ?? ($previewHeroImages[$document->website->website->templateId] ?? null);
    @endphp
    <x-public.skip-link />
    <x-public.navbar :document="$document" :blog-enabled="false" blog-url="#" />

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
                    <x-public.hero :section="$section" :document="$document" :preview-image-url="$previewHeroImage" />
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
                    @if ($section->images !== [])
                        <x-public.gallery :section="$section" :asset-urls="$document->assetUrls" :asset-dimensions="$document->assetDimensions" :template-id="$document->website->website->templateId" />
                    @endif
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
    </main>

    <x-public.footer :document="$document" :contact-section="$contactSection" />
    <x-public.whatsapp-button :action="$document->contactActions->whatsApp" :button-style="$document->website->branding->whatsAppButtonStyle" />
</body>
</html>
