<section class="public-section anchor-section" id="faq" aria-labelledby="faq-title">
    <div class="public-container public-container--reading">
        <x-public.section-heading eyebrow="Helpful answers" title="Frequently asked questions" />
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
