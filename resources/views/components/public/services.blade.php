@php
    $presentation = match ($templateId ?? 'SYIFA_ESSENTIAL') {
        'SYIFA_CARE' => ['Care for every stage', 'How we can help', 'Thoughtful care for individuals and families, shaped around everyday health needs.'],
        'SYIFA_DENTAL' => ['Treatments', 'Dental care, clearly explained', 'Explore focused treatments delivered with precision, comfort and clear guidance.'],
        'SYIFA_AESTHETIC' => ['Curated treatments', 'A considered approach to aesthetic care', 'Personalised treatments designed around subtle, natural-looking outcomes.'],
        'SYIFA_SPECIALIST' => ['Clinical expertise', 'Specialist services', 'Focused assessment and treatment pathways led by experienced clinical professionals.'],
        default => ['Our care', 'Services', 'Explore the care available at our clinic.'],
    };
@endphp
<section class="public-section anchor-section" id="services" aria-labelledby="services-title">
    <div class="public-container">
        <x-public.section-heading :eyebrow="$presentation[0]" :title="$presentation[1]" :description="$presentation[2]" />
        <div class="card-grid card-grid--services card-grid--count-{{ min(count($section->services), 4) }}" role="list">
            @foreach ($section->services as $service)
                <article class="service-card {{ $service->featured ? 'service-card--featured' : '' }}" role="listitem">
                    <div class="service-card__heading">
                        <h3>{{ $service->displayName }}</h3>
                        @if ($service->featured)<span class="badge">Featured</span>@endif
                    </div>
                    @if ($service->shortDescription !== null)<p>{{ $service->shortDescription }}</p>@endif
                </article>
            @endforeach
        </div>
        <div class="section-action"><a class="button button--secondary" href="{{ $bookingUrl->value }}">Book an appointment</a></div>
    </div>
</section>
