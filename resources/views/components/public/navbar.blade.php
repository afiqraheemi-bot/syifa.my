@props(['document', 'blogEnabled' => false, 'blogUrl' => '/blog'])
@php
    $ms = $document->language === 'ms';
    $logoId = $document->website->header->logoAssetId;
    $logoUrl = $logoId === null ? null : ($document->assetUrls[$logoId] ?? null);
@endphp
<header class="site-header">
    <div class="public-container navbar">
        <a class="brand" href="{{ $document->context->url()->value }}" aria-label="{{ $document->website->header->clinicName }} {{ $ms ? 'laman utama' : 'home' }}">
            @if ($logoUrl !== null)
                <img class="brand__logo brand__logo--{{ $document->website->header->logoDisplaySize }}" src="{{ $logoUrl->value }}" alt="">
            @else
                <span class="brand__copy">
                    <strong>{{ $document->website->header->clinicName }}</strong>
                    @if ($document->website->header->tagline !== null)
                        <span>{{ $document->website->header->tagline }}</span>
                    @endif
                </span>
            @endif
        </a>
        <div class="navbar__cluster">
            <nav id="public-navigation" class="public-navigation" aria-label="{{ $ms ? 'Navigasi utama' : 'Primary navigation' }}" data-public-menu>
                <div class="public-navigation__links">
                    @foreach ($document->navigation as $item)
                        @if ($item->route !== \App\Modules\WebsiteBuilder\Application\Delivery\PublicRoute::Booking)
                            <a href="{{ $item->url->value }}">{{ $item->label }}</a>
                        @endif
                    @endforeach
                    @if($blogEnabled)
                        <a href="{{ $blogUrl }}">Blog</a>
                    @endif
                </div>
            </nav>
            <div class="navbar__actions">
                <a class="button button--primary navbar__booking" href="{{ $document->bookingDestination->value }}" aria-label="{{ $ms ? 'Tempah Appointment' : 'Book Appointment' }}">
                    <span class="navbar__booking-label">{{ $ms ? 'Tempah Appointment' : 'Book Appointment' }}</span>
                    <span class="navbar__booking-label navbar__booking-label--short" aria-hidden="true">{{ $ms ? 'Tempah' : 'Book' }}</span>
                </a>
                <button class="menu-toggle" type="button" aria-label="{{ $ms ? 'Buka menu navigasi' : 'Open navigation menu' }}" aria-expanded="false" aria-controls="public-navigation" data-public-menu-toggle>
                    <span class="menu-toggle__icon" aria-hidden="true"><span></span><span></span><span></span></span>
                    <span class="menu-toggle__label">Menu</span>
                </button>
            </div>
        </div>
    </div>
</header>
