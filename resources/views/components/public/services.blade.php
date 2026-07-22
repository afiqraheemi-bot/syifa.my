<section class="public-section anchor-section" id="services" aria-labelledby="services-title">
    <div class="public-container">
        <x-public.section-heading eyebrow="Our care" title="Services" description="Explore the care available at our clinic." />
        <div class="card-grid card-grid--services" role="list">
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
        <div class="section-action"><a class="text-action" href="{{ $bookingUrl->value }}">Book an appointment <span aria-hidden="true">→</span></a></div>
    </div>
</section>
