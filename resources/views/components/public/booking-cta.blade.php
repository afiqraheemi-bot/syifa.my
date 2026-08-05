<section class="public-section public-section--booking anchor-section" id="booking" aria-labelledby="booking-title">
    <div class="public-container">
        <div class="booking-panel">
            <div>
                <p class="eyebrow">Your next step</p>
                <h2 id="booking-title">{{ $section->heading }}</h2>
                <p>{{ $section->description }}</p>
            </div>
            <div class="booking-panel__actions">
                <a class="button button--primary button--inverse" href="{{ $bookingUrl->value }}">{{ $section->buttonLabel }}</a>
                @if ($phoneUrl !== null)<a class="text-action text-action--inverse" href="{{ $phoneUrl }}">Prefer to call?</a>@endif
            </div>
        </div>
    </div>
</section>
