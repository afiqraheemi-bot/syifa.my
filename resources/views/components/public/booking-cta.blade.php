@props(['section', 'bookingUrl', 'phoneUrl' => null, 'language' => 'en'])
@php
    $heading = $language === 'ms' ? str_ireplace('Book an appointment', 'Tempah appointment', $section->heading) : $section->heading;
    $buttonLabel = $language === 'ms' ? str_ireplace(['Book now', 'Book an appointment'], ['Tempah sekarang', 'Tempah appointment'], $section->buttonLabel) : $section->buttonLabel;
@endphp
<section class="public-section public-section--booking anchor-section" id="booking" aria-labelledby="booking-title">
    <div class="public-container">
        <div class="booking-panel">
            <div>
                <p class="eyebrow">{{ $language === 'ms' ? 'Langkah seterusnya' : 'Your next step' }}</p>
                <h2 id="booking-title">{{ $heading }}</h2>
                <p>{{ $section->description }}</p>
            </div>
            <div class="booking-panel__actions">
                <a class="button button--primary button--inverse" href="{{ $bookingUrl->value }}">{{ $buttonLabel }}</a>
                @if ($phoneUrl !== null)<a class="text-action text-action--inverse" href="{{ $phoneUrl }}">{{ $language === 'ms' ? 'Lebih selesa menelefon?' : 'Prefer to call?' }}</a>@endif
            </div>
        </div>
    </div>
</section>
