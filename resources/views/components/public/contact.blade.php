<section class="public-section public-section--contact anchor-section" id="contact" aria-labelledby="contact-title">
    <div class="public-container contact-layout">
        <div class="contact-layout__intro">
            <p class="eyebrow">Plan your visit</p>
            <h2 id="contact-title">Contact the clinic</h2>
            @if ($section->address !== null)<p class="prose-lead">{{ $section->address }}</p>@endif
            <div class="contact-actions" aria-label="Clinic contact options">
                @if ($actions->telephone !== null)<a class="contact-action" href="{{ $actions->telephone }}"><x-public.icon name="phone" /><span class="contact-action__copy"><span>Call the clinic</span><strong>{{ $section->contactPhone }}</strong></span></a>@endif
                @if ($actions->email !== null)<a class="contact-action" href="{{ $actions->email }}"><x-public.icon name="mail" /><span class="contact-action__copy"><span>Email the clinic</span><strong>{{ $section->contactEmail }}</strong></span></a>@endif
                @if ($actions->whatsApp !== null)<a class="contact-action" href="{{ $actions->whatsApp->value }}"><x-public.icon name="message" /><span class="contact-action__copy"><span>WhatsApp</span><strong>{{ $section->whatsAppNumber }}</strong></span></a>@endif
                @if ($actions->directions !== null)<a class="button button--secondary" href="{{ $actions->directions->value }}" rel="noopener noreferrer">Get directions <x-public.icon name="external" /></a>@endif
            </div>
            @if ($actions->whatsApp !== null)<p class="contact-actions__hint">Have a quick question before booking? Message us on WhatsApp — we're happy to help.</p>@endif
            <a class="button button--secondary" href="{{ $bookingUrl->value }}">Book an appointment</a>
        </div>
        <div class="contact-layout__details">
            <x-public.business-hours :hours="$section->businessHours" />
            @if ($section->socialLinks !== [])
                <div class="social-links"><h3>Follow the clinic</h3><ul>
                    @foreach ($section->socialLinks as $channel => $url)<li><a href="{{ $url }}" rel="noopener noreferrer">{{ ucfirst($channel) }} <x-public.icon name="external" /></a></li>@endforeach
                </ul></div>
            @endif
        </div>
    </div>
</section>
