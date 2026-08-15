@php
    $presentation = match ($templateId ?? 'SYIFA_ESSENTIAL') {
        'SYIFA_CARE' => ['Here when you need us', 'Questions families often ask', 'Simple, reassuring answers to help you prepare for your visit.'],
        'SYIFA_DENTAL' => ['Before your appointment', 'Dental treatment questions', 'Straightforward guidance about visits, treatments and what you can expect.'],
        'SYIFA_AESTHETIC' => ['Before we begin', 'Your consultation questions', 'Helpful guidance about consultations, treatments and personalised recommendations.'],
        'SYIFA_SPECIALIST' => ['Patient information', 'Preparing for specialist care', 'Clear information to support referrals, appointments and the next stage of care.'],
        default => ['Helpful answers', 'Frequently asked questions', 'Clear answers to help you feel informed before your visit.'],
    };
@endphp
<section class="public-section anchor-section" id="faq" aria-labelledby="faq-title">
    <div class="public-container public-container--reading">
        <x-public.section-heading :eyebrow="$presentation[0]" :title="$presentation[1]" :description="$presentation[2]" />
        <div class="faq-list">
            @foreach ($section->entries as $entry)
                <details class="faq-item">
                    <summary><span>{{ $entry->question }}</span><span aria-hidden="true">+</span></summary>
                    <div class="faq-item__answer"><p>{{ $entry->answer }}</p></div>
                </details>
            @endforeach
        </div>
    </div>
</section>
