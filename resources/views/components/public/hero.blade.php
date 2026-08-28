@props(['section', 'document', 'previewImageUrl' => null])

@php
    $imageUrl = $section->heroImageAssetId === null ? null : ($document->assetUrls[$section->heroImageAssetId] ?? null);
    $imageSrc = $previewImageUrl ?? $imageUrl?->value;
    $imageDimensions = $section->heroImageAssetId === null ? [null, null] : ($document->assetDimensions[$section->heroImageAssetId] ?? [null, null]);
    $navigation = collect($document->navigation);
    $legacyRoutes = [
        '/about' => \App\Modules\WebsiteBuilder\Application\Delivery\PublicRoute::About,
        '/services' => \App\Modules\WebsiteBuilder\Application\Delivery\PublicRoute::Services,
        '/doctors' => \App\Modules\WebsiteBuilder\Application\Delivery\PublicRoute::Doctors,
        '/contact' => \App\Modules\WebsiteBuilder\Application\Delivery\PublicRoute::Contact,
    ];
    $resolveTarget = static function (?string $target) use ($document, $navigation, $legacyRoutes): string {
        if ($target === null || $target === '/booking') {
            return $document->bookingDestination->value;
        }
        if (isset($legacyRoutes[$target])) {
            return $navigation->first(fn ($item) => $item->route === $legacyRoutes[$target])?->url->value
                ?? $document->context->url('/#'.ltrim($target, '/'))->value;
        }
        if (str_starts_with($target, 'https://')) {
            return $target;
        }

        return $document->context->url($target)->value;
    };
    $primaryTarget = $resolveTarget($section->primaryCtaTarget);
    $secondaryTarget = $section->secondaryCtaTarget === null ? null : $resolveTarget($section->secondaryCtaTarget);
    $phone = $document->website->footer->contactPhone;
    $address = $document->website->footer->address;
    $todayHours = $document->todayHoursLabel;
    $templateId = $document->website->website->templateId;
    $showTemplateVisual = $imageSrc !== null || $templateId !== 'SYIFA_ESSENTIAL';
    $ms = $document->language === 'ms';
    $primaryLabel = $section->primaryCtaLabel ?? 'Book Appointment';
    $secondaryLabel = $section->secondaryCtaLabel;
    if ($ms) {
        $primaryLabel = str_ireplace(['Book Appointment', 'Book now'], ['Tempah Appointment', 'Tempah sekarang'], $primaryLabel);
        $secondaryLabel = $secondaryLabel === null ? null : str_ireplace(['Explore services', 'Learn more'], ['Terokai servis', 'Ketahui lanjut'], $secondaryLabel);
    }
@endphp
<section class="hero" id="home" aria-labelledby="hero-title">
    <div class="public-container hero__layout {{ $showTemplateVisual ? '' : 'hero__layout--text' }}">
        <div class="hero__content">
            <p class="eyebrow">{{ $document->website->branding->clinicName }}</p>
            <h1 id="hero-title">{{ $section->headline }}</h1>
            @if ($section->subheadline !== null)<p class="hero__lead">{{ $section->subheadline }}</p>@endif
            <div class="hero__actions">
                <a class="button button--primary" href="{{ $primaryTarget }}" @if(str_starts_with($primaryTarget, 'https://') && !str_starts_with($primaryTarget, $document->context->origin())) target="_blank" rel="noopener noreferrer" @endif>{{ $primaryLabel }}</a>
                @if ($section->secondaryCtaLabel !== null && $secondaryTarget !== null)
                    <a class="button button--secondary" href="{{ $secondaryTarget }}" @if(str_starts_with($secondaryTarget, 'https://') && !str_starts_with($secondaryTarget, $document->context->origin())) target="_blank" rel="noopener noreferrer" @endif>{{ $secondaryLabel }}</a>
                @endif
            </div>
            @if ($phone !== null || $todayHours !== null || $address !== null)
                <ul class="hero__trust" aria-label="{{ $ms ? 'Maklumat klinik' : 'Clinic information' }}">
                    @if ($phone !== null)<li><x-public.icon name="phone" /><span>{{ $phone }}</span></li>@endif
                    @if ($todayHours !== null)<li><x-public.icon name="clock" /><span>{{ $todayHours }}</span></li>@endif
                    @if ($address !== null)<li><x-public.icon name="location" /><span>{{ $address }}</span></li>@endif
                </ul>
            @endif
        </div>
        @if ($showTemplateVisual)
            <div class="hero__media {{ $imageSrc === null ? 'hero__media--fallback' : '' }}">
                @if ($imageSrc !== null)
                    <x-public.responsive-image :url="$imageSrc" alt="" :width="$imageDimensions[0]" :height="$imageDimensions[1]" :priority="true" class="hero__image" />
                @else
                    <span class="hero__media-symbol" aria-hidden="true"><x-public.icon name="medical" /></span>
                @endif
            </div>
        @endif
    </div>
</section>
