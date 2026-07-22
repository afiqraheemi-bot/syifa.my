<section class="public-section public-section--subtle anchor-section" id="doctors" aria-labelledby="doctors-title">
    <div class="public-container">
        <x-public.section-heading eyebrow="Your care team" title="Meet our doctors" />
        <div class="card-grid card-grid--doctors card-grid--count-{{ min(count($section->doctors), 4) }}" role="list">
            @foreach ($section->doctors as $doctor)
                @php
                    $photoUrl = $doctor->photoAssetId === null ? null : ($assetUrls[$doctor->photoAssetId] ?? null);
                    $photoDimensions = $doctor->photoAssetId === null ? [null, null] : ($assetDimensions[$doctor->photoAssetId] ?? [null, null]);
                @endphp
                <article class="doctor-card {{ $photoUrl === null ? 'doctor-card--text' : '' }}" role="listitem">
                    @if ($photoUrl !== null)
                        <div class="doctor-card__media"><x-public.responsive-image :url="$photoUrl->value" :alt="$doctor->name" :width="$photoDimensions[0]" :height="$photoDimensions[1]" class="doctor-card__image" /></div>
                    @endif
                    <div class="doctor-card__content">
                        <h3>{{ $doctor->name }}</h3>
                        @if ($doctor->professionalTitle !== null)<p>{{ $doctor->professionalTitle }}</p>@endif
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
