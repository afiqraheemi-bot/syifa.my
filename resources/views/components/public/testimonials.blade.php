<section class="public-section anchor-section" id="testimonials" aria-labelledby="testimonials-title">
    <div class="public-container">
        <x-public.section-heading eyebrow="Patient perspectives" title="What patients share" />
        <div class="card-grid card-grid--testimonials card-grid--count-{{ min(count($section->testimonials), 4) }}" role="list">
            @foreach ($section->testimonials as $testimonial)
                <figure class="testimonial-card" role="listitem">
                    <blockquote><p>{{ $testimonial->quote }}</p></blockquote>
                    <figcaption>— {{ $testimonial->authorName }}</figcaption>
                </figure>
            @endforeach
        </div>
    </div>
</section>
