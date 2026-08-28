@props(['theme', 'robots' => 'index,follow'])
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Book an Appointment' }}</title>
    <meta name="robots" content="{{ $robots }}">
    <style>:root{--brand-primary:{{ $theme->brandTokens->primary }};--brand-primary-hover:{{ $theme->brandTokens->primaryHover }};--brand-primary-active:{{ $theme->brandTokens->primaryActive }};--brand-on-primary:{{ $theme->brandTokens->onPrimary }};--brand-secondary:{{ $theme->brandTokens->secondary }};--brand-on-secondary:{{ $theme->brandTokens->onSecondary }};}</style>
    @unless (app()->environment('testing')) @vite(['resources/css/public-website.css', 'resources/css/public-booking.css', 'resources/js/public-website.js']) @endunless
</head>
<body class="public-site booking-flow" data-template="{{ $theme->templateId }}">
    <x-public.skip-link />

    <header class="booking-site-header">
        <div class="booking-site-header__inner">
            <a class="booking-back-home" href="{{ route('public-website.home') }}" data-booking-history-back>
                <span class="booking-back-home__icon" aria-hidden="true">←</span>
                <span>Back</span>
            </a>
            <span class="booking-secure-label">
                <span aria-hidden="true">&#10003;</span>
                Secure appointment request
            </span>
        </div>
    </header>

    <main id="main-content" class="booking-screen">
        <div class="booking-container">
            <div class="booking-card">
                @if ($errors->any())
                    <x-public.booking.error-banner :errors="$errors" />
                @endif

                {{ $slot }}
            </div>
        </div>
    </main>
</body>
</html>
