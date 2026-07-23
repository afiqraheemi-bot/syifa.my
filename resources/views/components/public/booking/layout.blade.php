<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Book an Appointment' }}</title>
    <meta name="robots" content="noindex">
    @unless (app()->environment('testing')) @vite('resources/js/public-website.js') @endunless
</head>
<body class="public-site booking-flow">
    <x-public.skip-link />

    <main id="main-content" class="booking-screen">
        <div class="booking-container">
            @if ($errors->any())
                <x-public.booking.error-banner :errors="$errors" />
            @endif

            {{ $slot }}
        </div>
    </main>
</body>
</html>
