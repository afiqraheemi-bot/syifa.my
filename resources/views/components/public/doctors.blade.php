@php
    $presentation = match ($templateId ?? 'SYIFA_ESSENTIAL') {
        'SYIFA_CARE' => ['People who care', 'A team that listens', 'Meet the doctors committed to making every visit feel clear, comfortable and personal.'],
        'SYIFA_DENTAL' => ['Clinical team', 'Your dental professionals', 'Experienced clinicians combining careful assessment with a comfortable treatment experience.'],
        'SYIFA_AESTHETIC' => ['Our practitioners', 'Expertise, with a personal touch', 'Meet the professionals who take time to understand your goals and guide every treatment.'],
        'SYIFA_SPECIALIST' => ['Consultant team', 'Specialists dedicated to your care', 'Experienced professionals providing focused clinical insight, assessment and treatment.'],
        default => ['Your care team', 'Meet our doctors', 'Get to know the professionals dedicated to your care and wellbeing.'],
    };
    if (($language ?? 'en') === 'ms') {
        $presentation = match ($templateId ?? 'SYIFA_ESSENTIAL') {
            'SYIFA_DENTAL' => ['Pasukan klinikal', 'Profesional pergigian anda', 'Kenali profesional yang mengutamakan penilaian teliti dan pengalaman rawatan yang selesa.'],
            'SYIFA_AESTHETIC' => ['Pengamal kami', 'Kepakaran dengan sentuhan peribadi', 'Kenali profesional yang memahami matlamat anda dan membimbing setiap langkah penjagaan.'],
            'SYIFA_SPECIALIST' => ['Pasukan konsultan', 'Pakar yang komited terhadap penjagaan anda', 'Profesional berpengalaman yang menyediakan pandangan klinikal dan penilaian berfokus.'],
            default => ['Pasukan penjagaan anda', 'Kenali doktor kami', 'Kenali profesional yang berdedikasi terhadap penjagaan dan kesejahteraan anda.'],
        };
    }
@endphp
<section class="public-section public-section--subtle anchor-section" id="doctors" aria-labelledby="doctors-title">
    <div class="public-container">
        <x-public.section-heading
            :eyebrow="$presentation[0]"
            :title="$presentation[1]"
            :description="$presentation[2]"
        />
        <div class="card-grid card-grid--doctors card-grid--count-{{ min(count($section->doctors), 4) }}" role="list">
            @foreach ($section->doctors as $doctor)
                @php
                    $photoUrl = $doctor->photoAssetId === null ? null : ($assetUrls[$doctor->photoAssetId] ?? null);
                    $photoDimensions = $doctor->photoAssetId === null ? [null, null] : ($assetDimensions[$doctor->photoAssetId] ?? [null, null]);
                @endphp
                <article class="doctor-card {{ $photoUrl === null ? 'doctor-card--text' : '' }}" role="listitem">
                    @if ($photoUrl !== null)
                        <div class="doctor-card__media"><x-public.responsive-image :url="$photoUrl->value" :alt="$doctor->name" :width="$photoDimensions[0]" :height="$photoDimensions[1]" class="doctor-card__image" /></div>
                    @else
                        <div class="doctor-card__fallback" aria-hidden="true">{{ mb_strtoupper(mb_substr(trim($doctor->name), 0, 1)) }}</div>
                    @endif
                    <div class="doctor-card__content">
                        <h3>{{ $doctor->name }}</h3>
                        @if ($doctor->professionalTitle !== null)<p class="doctor-card__title">{{ $doctor->professionalTitle }}</p>@endif
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
