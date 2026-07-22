@php
    $imageUrl = $section->heroImageAssetId === null ? null : ($document->assetUrls[$section->heroImageAssetId] ?? null);
    $imageDimensions = $section->heroImageAssetId === null ? [null, null] : ($document->assetDimensions[$section->heroImageAssetId] ?? [null, null]);
    $services = collect($document->navigation)->first(fn ($item) => $item->route === \App\Modules\WebsiteBuilder\Application\Delivery\PublicRoute::Services);
    $secondaryUrl = $services?->url;
@endphp
<section class="hero" id="home" aria-labelledby="hero-title">
    <div class="public-container hero__layout {{ $imageUrl === null ? 'hero__layout--text' : '' }}">
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
            <div class="hero__trust">
                @if ($document->website->footer->contactPhone !== null)<span>Direct clinic contact</span>@endif
                @if ($document->website->footer->address !== null)<span>{{ $document->website->footer->address }}</span>@endif
            </div>
        </div>
        @if ($imageUrl !== null)
            <div class="hero__media">
                <x-public.responsive-image :url="$imageUrl->value" alt="" :width="$imageDimensions[0]" :height="$imageDimensions[1]" :priority="true" class="hero__image" />
            </div>
        @endif
    </div>
</section>
