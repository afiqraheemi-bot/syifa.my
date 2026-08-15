@php
    $presentation = match ($templateId ?? 'SYIFA_ESSENTIAL') {
        'SYIFA_CARE' => ['Stories from our community', 'Care patients remember', 'Personal experiences from patients and families who placed their trust in our team.'],
        'SYIFA_DENTAL' => ['Patient confidence', 'Experiences behind every smile', 'What patients share about their treatment, comfort and care journey with us.'],
        'SYIFA_AESTHETIC' => ['Client stories', 'Confidence, thoughtfully restored', 'Reflections from clients who valued a considered and personal treatment experience.'],
        'SYIFA_SPECIALIST' => ['Patient outcomes', 'Trusted through every stage', 'Experiences from patients supported by our specialist team and structured care pathways.'],
        default => ['Patient perspectives', 'What patients share', 'Real experiences shared by patients who trusted our team with their care.'],
    };
@endphp
<section class="public-section anchor-section" id="testimonials" aria-labelledby="testimonials-title">
    <div class="public-container">
        <x-public.section-heading :eyebrow="$presentation[0]" :title="$presentation[1]" :description="$presentation[2]" />
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
