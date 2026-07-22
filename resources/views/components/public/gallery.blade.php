<section class="public-section public-section--subtle anchor-section" id="gallery" aria-labelledby="gallery-title">
    <div class="public-container public-container--wide">
        <x-public.section-heading eyebrow="Our environment" title="A space designed for care" />
        <div class="gallery-grid">
            @foreach ($section->images as $image)
                @php
                    $imageUrl = $assetUrls[$image->assetId];
                    $imageDimensions = $assetDimensions[$image->assetId] ?? [null, null];
                @endphp
                <figure class="gallery-item">
                    <x-public.responsive-image :url="$imageUrl->value" :alt="$image->decorative ? '' : ($image->altText ?? '')" :width="$imageDimensions[0]" :height="$imageDimensions[1]" class="gallery-item__image" />
                    @if ($image->caption !== null)<figcaption>{{ $image->caption }}</figcaption>@endif
                </figure>
            @endforeach
        </div>
    </div>
</section>
