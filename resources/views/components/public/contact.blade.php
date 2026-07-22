<section class="public-section public-section--contact anchor-section" id="contact" aria-labelledby="contact-title">
    <div class="public-container contact-layout">
        <div class="contact-layout__intro">
            <p class="eyebrow">Plan your visit</p>
            <h2 id="contact-title">Contact the clinic</h2>
            @if ($section->address !== null)<p class="prose-lead">{{ $section->address }}</p>@endif
            <div class="contact-actions" aria-label="Clinic contact options">
                @if ($actions->telephone !== null)<a class="contact-action" href="{{ $actions->telephone }}"><span>Call the clinic</span><strong>{{ $section->contactPhone }}</strong></a>@endif
                @if ($actions->email !== null)<a class="contact-action" href="{{ $actions->email }}"><span>Email the clinic</span><strong>{{ $section->contactEmail }}</strong></a>@endif
                @if ($actions->whatsApp !== null)<a class="contact-action" href="{{ $actions->whatsApp->value }}"><span>WhatsApp</span><strong>{{ $section->whatsAppNumber }}</strong></a>@endif
                @if ($actions->directions !== null)<a class="button button--secondary" href="{{ $actions->directions->value }}" rel="noopener noreferrer">Get directions</a>@endif
            </div>
            <a class="text-action" href="{{ $bookingUrl->value }}">Book an appointment <span aria-hidden="true">→</span></a>
        </div>
        <div class="contact-layout__details">
            <x-public.business-hours :hours="$section->businessHours" />
            @if ($section->socialLinks !== [])
                <div class="social-links"><h3>Follow the clinic</h3><ul>
                    @foreach ($section->socialLinks as $channel => $url)<li><a href="{{ $url }}" rel="noopener noreferrer">{{ ucfirst($channel) }}</a></li>@endforeach
                </ul></div>
            @endif
        </div>
    </div>
</section>
