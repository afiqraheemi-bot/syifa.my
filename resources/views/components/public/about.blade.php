@php
    $imageUrl = $section->imageAssetId === null ? null : ($assetUrls[$section->imageAssetId] ?? null);
    $imageDimensions = $section->imageAssetId === null ? [null, null] : ($assetDimensions[$section->imageAssetId] ?? [null, null]);
@endphp
<section class="public-section public-section--subtle anchor-section" id="about" aria-labelledby="about-title">
    <div class="public-container about {{ $imageUrl === null ? 'about--text' : '' }}">
        <div class="about__content">
            <p class="eyebrow">Care with purpose</p>
            <h2 id="about-title">{{ $section->heading }}</h2>
            <p class="prose-lead">{{ $section->description }}</p>
        </div>
        @if ($imageUrl !== null)
            <div class="about__media"><x-public.responsive-image :url="$imageUrl->value" alt="" :width="$imageDimensions[0]" :height="$imageDimensions[1]" class="about__image" /></div>
        @endif
    </div>
</section>
