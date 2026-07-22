<footer class="site-footer">
    <div class="public-container site-footer__grid">
        <div class="site-footer__brand">
            <a href="{{ $document->context->url()->value }}">{{ $document->website->footer->clinicName }}</a>
            @if ($document->website->branding->tagline !== null)<p>{{ $document->website->branding->tagline }}</p>@endif
        </div>
        @if ($document->navigation !== [])
            <nav aria-label="Footer navigation"><h2>Explore</h2><ul>
                @foreach ($document->navigation as $item)<li><a href="{{ $item->url->value }}">{{ $item->label }}</a></li>@endforeach
            </ul></nav>
        @endif
        @if ($document->website->footer->contactPhone !== null || $document->website->footer->contactEmail !== null || $document->website->footer->address !== null)
            <div><h2>Contact</h2><address>
                @if ($document->website->footer->address !== null)<p class="footer-line"><x-public.icon name="location" />{{ $document->website->footer->address }}</p>@endif
                @if ($document->contactActions->telephone !== null)<a class="footer-line" href="{{ $document->contactActions->telephone }}"><x-public.icon name="phone" />{{ $document->website->footer->contactPhone }}</a>@endif
                @if ($document->contactActions->email !== null)<a class="footer-line" href="{{ $document->contactActions->email }}"><x-public.icon name="mail" />{{ $document->website->footer->contactEmail }}</a>@endif
            </address></div>
        @endif
        @if ($document->website->footer->businessHours !== [])
            <x-public.business-hours :hours="$document->website->footer->businessHours" />
        @endif
    </div>
    <div class="public-container site-footer__legal">
        <p>© {{ $document->website->publication->publishedAt->format('Y') }} {{ $document->website->footer->clinicName }}</p>
        @if ($document->legalUrls !== [])
            <nav aria-label="Legal">
                @if (isset($document->legalUrls['privacy']))<a href="{{ $document->legalUrls['privacy']->value }}">Privacy</a>@endif
                @if (isset($document->legalUrls['terms']))<a href="{{ $document->legalUrls['terms']->value }}">Terms</a>@endif
            </nav>
        @endif
    </div>
</footer>
