@php
    $ms = ($language ?? 'en') === 'ms';
    $presentation = $ms ? match ($templateId ?? 'SYIFA_ESSENTIAL') {
        'SYIFA_CARE' => ['Penjagaan setiap peringkat', 'Cara kami membantu', 'Penjagaan teliti untuk individu dan keluarga, disesuaikan dengan keperluan kesihatan harian.'],
        'SYIFA_DENTAL' => ['Rawatan', 'Penjagaan pergigian yang jelas', 'Terokai rawatan pergigian dengan penerangan yang jelas serta mengutamakan keselesaan anda.'],
        'SYIFA_AESTHETIC' => ['Rawatan terpilih', 'Pendekatan teliti untuk penjagaan estetik', 'Penjagaan yang diperibadikan mengikut keperluan dan matlamat individu.'],
        'SYIFA_SPECIALIST' => ['Kepakaran klinikal', 'Servis pakar', 'Penilaian dan laluan penjagaan berfokus bersama profesional klinikal berpengalaman.'],
        default => ['Penjagaan kami', 'Servis', 'Terokai penjagaan yang tersedia di klinik kami.'],
    } : match ($templateId ?? 'SYIFA_ESSENTIAL') {
        'SYIFA_CARE' => ['Care for every stage', 'How we can help', 'Thoughtful care for individuals and families, shaped around everyday health needs.'],
        'SYIFA_DENTAL' => ['Treatments', 'Dental care, clearly explained', 'Explore focused treatments delivered with precision, comfort and clear guidance.'],
        'SYIFA_AESTHETIC' => ['Curated treatments', 'A considered approach to aesthetic care', 'Personalised treatments designed around subtle, natural-looking outcomes.'],
        'SYIFA_SPECIALIST' => ['Clinical expertise', 'Specialist services', 'Focused assessment and treatment pathways led by experienced clinical professionals.'],
        default => ['Our care', 'Services', 'Explore the care available at our clinic.'],
    };
    $addressParts = array_values(array_filter(array_map('trim', explode(',', $address ?? ''))));
    if (isset($addressParts[count($addressParts) - 1]) && preg_match('/^(Malaysia|MY)$/i', $addressParts[count($addressParts) - 1])) array_pop($addressParts);
    if ($addressParts !== []) array_pop($addressParts);
    $city = $addressParts === [] ? null : trim((string) preg_replace('/\b\d{5}\b/', '', array_pop($addressParts)));
@endphp
<section class="public-section anchor-section" id="services" aria-labelledby="services-title">
    <div class="public-container">
        <x-public.section-heading :eyebrow="$presentation[0]" :title="$presentation[1]" :description="$presentation[2]" />
        <div class="card-grid card-grid--services card-grid--count-{{ min(count($section->services), 4) }}" role="list">
            @foreach ($section->services as $service)
                @php $serviceName = $ms ? str_ireplace(['General Consultation', 'Dental Consultation', 'Health Screening'], ['Konsultasi Am', 'Konsultasi Pergigian', 'Saringan Kesihatan'], $service->displayName) : $service->displayName; @endphp
                <article class="service-card {{ $service->featured ? 'service-card--featured' : '' }}" role="listitem">
                    <div class="service-card__heading">
                        <h3>{{ $serviceName }}</h3>
                        @if ($service->featured)<span class="badge">{{ $ms ? 'Pilihan' : 'Featured' }}</span>@endif
                    </div>
                    @if ($service->shortDescription !== null)
                        <p>{{ $service->shortDescription }}</p>
                    @else
                        <p>{{ $ms
                            ? $serviceName.' membantu pesakit'.($city ? ' di '.$city : '').' mendapatkan penilaian awal dan panduan penjagaan yang sesuai dalam suasana klinik yang selesa serta profesional.'
                            : $serviceName.' helps patients'.($city ? ' in '.$city : '').' access an initial assessment and suitable care guidance in a comfortable, professional clinic setting.' }}</p>
                    @endif
                </article>
            @endforeach
        </div>
        <div class="section-action"><a class="button button--secondary" href="{{ $bookingUrl->value }}">{{ $ms ? 'Tempah appointment' : 'Book an appointment' }}</a></div>
    </div>
</section>
