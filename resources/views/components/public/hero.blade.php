@php
    $imageUrl = $section->heroImageAssetId === null ? null : ($document->assetUrls[$section->heroImageAssetId] ?? null);
    $imageDimensions = $section->heroImageAssetId === null ? [null, null] : ($document->assetDimensions[$section->heroImageAssetId] ?? [null, null]);
    $services = collect($document->navigation)->first(fn ($item) => $item->route === \App\Modules\WebsiteBuilder\Application\Delivery\PublicRoute::Services);
    $secondaryUrl = $services?->url;
    $phone = $document->website->footer->contactPhone;
    $address = $document->website->footer->address;
    $todayHours = $document->todayHoursLabel;
    $templateId = $document->website->website->templateId;
    $showTemplateVisual = $imageUrl !== null || $templateId !== 'SYIFA_ESSENTIAL';
@endphp
<section class="hero" id="home" aria-labelledby="hero-title">
    <div class="public-container hero__layout {{ $showTemplateVisual ? '' : 'hero__layout--text' }}">
        <div class="hero__content">
            <p class="eyebrow">{{ $document->website->branding->clinicName }}</p>
            <h1 id="hero-title">{{ $section->headline }}</h1>
            @if ($section->subheadline !== null)<p class="hero__lead">{{ $section->subheadline }}</p>@endif
            <div class="hero__actions">
                <a class="button button--primary" href="{{ $document->bookingDestination->value }}">{{ $section->primaryCtaLabel ?? 'Book Appointment' }}</a>
                @if ($section->secondaryCtaLabel !== null && $secondaryUrl !== null)
                    <a class="button button--secondary" href="{{ $secondaryUrl->value }}">{{ $section->secondaryCtaLabel }}</a>
                @endif
            </div>
            @if ($phone !== null || $todayHours !== null || $address !== null)
                <ul class="hero__trust" aria-label="Clinic information">
                    @if ($phone !== null)<li><x-public.icon name="phone" /><span>{{ $phone }}</span></li>@endif
                    @if ($todayHours !== null)<li><x-public.icon name="clock" /><span>{{ $todayHours }}</span></li>@endif
                    @if ($address !== null)<li><x-public.icon name="location" /><span>{{ $address }}</span></li>@endif
                </ul>
            @endif
        </div>
        @if ($showTemplateVisual)
            <div class="hero__media {{ $imageUrl === null ? 'hero__media--fallback' : '' }}">
                @if ($imageUrl !== null)
                    <x-public.responsive-image :url="$imageUrl->value" alt="" :width="$imageDimensions[0]" :height="$imageDimensions[1]" :priority="true" class="hero__image" />
                @else
                    <span class="hero__media-symbol" aria-hidden="true"><x-public.icon name="medical" /></span>
                @endif
            </div>
        @endif
    </div>
</section>
