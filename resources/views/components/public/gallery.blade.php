@php
    $presentation = match ($templateId ?? 'SYIFA_ESSENTIAL') {
        'SYIFA_CARE' => ['A welcoming space', 'Comfort from the moment you arrive', 'A calm, family-friendly environment created to make every visit feel easier.'],
        'SYIFA_DENTAL' => ['Our clinic', 'Designed for comfort and confidence', 'A clean, modern environment supported by thoughtful clinical detail.'],
        'SYIFA_AESTHETIC' => ['The space', 'Private, calm and considered', 'An intimate environment designed around comfort, discretion and personal attention.'],
        'SYIFA_SPECIALIST' => ['Clinical environment', 'A setting designed for focused care', 'Professional spaces supporting precise assessment, treatment and patient comfort.'],
        default => ['Our environment', 'A space designed for care', 'Explore a clean, comfortable environment created to help every visit feel calm and welcoming.'],
    };
@endphp
<section class="public-section public-section--subtle anchor-section" id="gallery" aria-labelledby="gallery-title">
    <div class="public-container public-container--wide">
        <x-public.section-heading
            :eyebrow="$presentation[0]"
            :title="$presentation[1]"
            :description="$presentation[2]"
        />
        @php $resolvedImages = array_values(array_filter($section->images, static fn ($image) => array_key_exists($image->assetId, $assetUrls))); @endphp
        <div class="gallery-grid gallery-grid--count-{{ min(count($resolvedImages), 4) }}">
            @foreach ($resolvedImages as $image)
                @php
                    $imageUrl = $assetUrls[$image->assetId];
                    $imageDimensions = $assetDimensions[$image->assetId] ?? [null, null];
                    $displayAlt = $image->decorative ? ($image->caption ?? 'Clinic environment') : ($image->altText ?? 'Clinic environment');
                @endphp
                <figure class="gallery-item">
                    <button
                        type="button"
                        class="gallery-item__button"
                        aria-label="View image: {{ $displayAlt }}"
                        data-gallery-open
                        data-gallery-src="{{ $imageUrl->value }}"
                        data-gallery-alt="{{ $displayAlt }}"
                        data-gallery-caption="{{ $image->caption ?? '' }}"
                    >
                        <x-public.responsive-image :url="$imageUrl->value" :alt="$image->decorative ? '' : ($image->altText ?? '')" :width="$imageDimensions[0]" :height="$imageDimensions[1]" :priority="$loop->index < 4" class="gallery-item__image" />
                        <span class="gallery-item__view" aria-hidden="true">View</span>
                    </button>
                    @if ($image->caption !== null)<figcaption>{{ $image->caption }}</figcaption>@endif
                </figure>
            @endforeach
        </div>
    </div>

    <dialog class="gallery-lightbox" aria-label="Clinic environment image" data-gallery-dialog>
        <button type="button" class="gallery-lightbox__close" aria-label="Close image" data-gallery-close>×</button>
        <figure>
            <img src="" alt="" data-gallery-dialog-image>
            <figcaption data-gallery-dialog-caption></figcaption>
        </figure>
    </dialog>
</section>
