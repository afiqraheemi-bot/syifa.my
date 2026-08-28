@props(['document', 'contactSection' => null])

@php
    $ms = $document->language === 'ms';
    $footerRoutes = [
        \App\Modules\WebsiteBuilder\Application\Delivery\PublicRoute::About,
        \App\Modules\WebsiteBuilder\Application\Delivery\PublicRoute::Services,
        \App\Modules\WebsiteBuilder\Application\Delivery\PublicRoute::Doctors,
        \App\Modules\WebsiteBuilder\Application\Delivery\PublicRoute::Contact,
        \App\Modules\WebsiteBuilder\Application\Delivery\PublicRoute::Booking,
    ];
    $footerNavigation = array_values(array_filter(
        $document->navigation,
        static fn ($item): bool => in_array($item->route, $footerRoutes, true),
    ));
    $businessHours = $contactSection !== null && $contactSection->businessHours !== []
        ? $contactSection->businessHours
        : $document->website->footer->businessHours;
    $logoId = $document->website->header->logoAssetId;
    $logoUrl = $logoId === null ? null : ($document->assetUrls[$logoId] ?? null);
@endphp

<footer
    @if ($contactSection !== null) id="contact" @endif
    class="site-footer{{ $contactSection !== null ? ' site-footer--with-contact' : '' }}"
>
    @if ($contactSection !== null)
        <div class="public-container site-footer__visit" aria-labelledby="footer-contact-title">
            <div class="site-footer__visit-intro">
                <p class="eyebrow">{{ $ms ? 'Rancang kunjungan anda' : 'Plan your visit' }}</p>
                <h2 id="footer-contact-title">{{ $ms ? 'Hubungi klinik' : 'Contact the clinic' }}</h2>
                @if ($contactSection->address !== null)
                    <p class="site-footer__address">{{ $contactSection->address }}</p>
                @endif

                <div class="site-footer__contact-actions" aria-label="{{ $ms ? 'Pilihan untuk menghubungi klinik' : 'Clinic contact options' }}">
                    @if ($document->contactActions->telephone !== null)
                        <a class="footer-contact" href="{{ $document->contactActions->telephone }}">
                            <x-public.icon name="phone" />
                            <span><small>{{ $ms ? 'Telefon' : 'Call' }}</small><strong>{{ $contactSection->contactPhone }}</strong></span>
                        </a>
                    @endif
                    @if ($document->contactActions->email !== null)
                        <a class="footer-contact" href="{{ $document->contactActions->email }}">
                            <x-public.icon name="mail" />
                            <span><small>Email</small><strong>{{ $contactSection->contactEmail }}</strong></span>
                        </a>
                    @endif
                    @if ($document->contactActions->whatsApp !== null)
                        <a class="footer-contact" href="{{ $document->contactActions->whatsApp->value }}">
                            <x-public.icon name="message" />
                            <span><small>WhatsApp</small><strong>{{ $contactSection->whatsAppNumber }}</strong></span>
                        </a>
                    @endif
                </div>

                @if ($document->contactActions->directions !== null)
                    <a class="button button--secondary site-footer__directions" href="{{ $document->contactActions->directions->value }}" rel="noopener noreferrer">
                        {{ $ms ? 'Dapatkan arah' : 'Get directions' }} <x-public.icon name="external" />
                    </a>
                @endif
            </div>

            <div class="site-footer__visit-details">
                @if ($businessHours !== [])
                    <x-public.business-hours :hours="$businessHours" :language="$document->language" />
                @else
                    <div class="business-hours business-hours--unavailable">
                        <h3><x-public.icon name="clock" /> {{ $ms ? 'Waktu operasi' : 'Operating hours' }}</h3>
                        <p>{{ $ms ? 'Sila hubungi klinik untuk mengesahkan waktu operasi hari ini.' : 'Please call the clinic to confirm today’s operating hours.' }}</p>
                    </div>
                @endif
                @if ($contactSection->socialLinks !== [])
                    <div class="social-links">
                        <h3>{{ $ms ? 'Ikuti klinik' : 'Follow the clinic' }}</h3>
                        <ul>
                            @foreach ($contactSection->socialLinks as $channel => $url)
                                <li><a href="{{ $url }}" rel="noopener noreferrer">{{ ucfirst($channel) }} <x-public.icon name="external" /></a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div class="public-container site-footer__grid{{ $contactSection !== null ? ' site-footer__grid--compact' : '' }}">
        <div class="site-footer__brand">
            <a class="site-footer__brand-link" href="{{ $document->context->url()->value }}" aria-label="{{ $document->website->footer->clinicName }} home">
                @if ($logoUrl !== null)
                    <span class="site-footer__logo-frame">
                        <img class="site-footer__logo site-footer__logo--{{ $document->website->header->logoDisplaySize }}" src="{{ $logoUrl->value }}" alt="">
                    </span>
                @else
                    <span>{{ $document->website->footer->clinicName }}</span>
                @endif
            </a>
            @if ($document->website->branding->tagline !== null)<p>{{ $document->website->branding->tagline }}</p>@endif
        </div>

        @if ($footerNavigation !== [])
            <nav class="site-footer__navigation" aria-label="Footer navigation">
                <h2>{{ $ms ? 'Terokai' : 'Explore' }}</h2>
                <ul>
                    @foreach ($footerNavigation as $item)
                        <li><a href="{{ $item->url->value }}">{{ $item->label }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif

        @if ($contactSection === null && ($document->website->footer->contactPhone !== null || $document->website->footer->contactEmail !== null || $document->website->footer->address !== null))
            <div><h2>Contact</h2><address>
                @if ($document->website->footer->address !== null)<p class="footer-line"><x-public.icon name="location" />{{ $document->website->footer->address }}</p>@endif
                @if ($document->contactActions->telephone !== null)<a class="footer-line" href="{{ $document->contactActions->telephone }}"><x-public.icon name="phone" />{{ $document->website->footer->contactPhone }}</a>@endif
                @if ($document->contactActions->email !== null)<a class="footer-line" href="{{ $document->contactActions->email }}"><x-public.icon name="mail" />{{ $document->website->footer->contactEmail }}</a>@endif
            </address></div>
        @endif
        @if ($contactSection === null && $document->website->footer->businessHours !== [])
            <x-public.business-hours :hours="$document->website->footer->businessHours" :language="$document->language" />
        @endif
    </div>

    <div class="public-container site-footer__legal">
        <p>© {{ $document->website->publication->publishedAt->format('Y') }} {{ $document->website->footer->clinicName }}</p>
        @if ($document->legalUrls !== [])
            <nav aria-label="{{ $ms ? 'Perundangan' : 'Legal' }}">
                @if (isset($document->legalUrls['privacy']))<a href="{{ $document->legalUrls['privacy']->value }}">{{ $ms ? 'Privasi' : 'Privacy' }}</a>@endif
                @if (isset($document->legalUrls['terms']))<a href="{{ $document->legalUrls['terms']->value }}">{{ $ms ? 'Terma' : 'Terms' }}</a>@endif
            </nav>
        @endif
    </div>
</footer>
