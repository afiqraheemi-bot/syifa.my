<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Draft Preview · {{ $document->head->title }}</title>
    <meta name="description" content="{{ $document->head->description }}">
    <meta name="robots" content="noindex,nofollow,noarchive">
    @unless (app()->environment('testing')) @vite(['resources/css/public-website.css', 'resources/js/public-website.js', 'resources/js/public-content-enhancements.js', 'resources/js/blog-slider.js']) @endunless
    <style>:root{--brand-primary:{{ $document->brandTokens->primary }};--brand-primary-hover:{{ $document->brandTokens->primaryHover }};--brand-primary-active:{{ $document->brandTokens->primaryActive }};--brand-on-primary:{{ $document->brandTokens->onPrimary }};--brand-secondary:{{ $document->brandTokens->secondary }};--brand-on-secondary:{{ $document->brandTokens->onSecondary }};}</style>
</head>
<body
    class="public-site"
    data-template="{{ strtolower(str_replace('_', '-', $document->website->website->templateId)) }}"
>
    <div
        role="status"
        class="draft-preview-indicator"
        style="position:sticky;top:0;z-index:1000;padding:.75rem 1rem;background:#18221f;color:#fff;text-align:center;font-weight:700"
    >
        Draft Preview · Not published
    </div>
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
        <x-public.blog-slider
            :articles="$latestBlogPosts ?? collect()"
            :article-url-prefix="$blogIndexUrl ?? '/blog'"
        />
    </main>

    <x-public.footer :document="$document" :contact-section="$contactSection" />
    <x-public.whatsapp-button :action="$document->contactActions->whatsApp" :button-style="$document->website->branding->whatsAppButtonStyle" />
</body>
</html>
