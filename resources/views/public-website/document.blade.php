<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document->head->title }}</title>
    <meta name="description" content="{{ $document->head->description }}">
    <meta name="robots" content="{{ $document->head->robots }}">
    <link rel="canonical" href="{{ $document->head->canonicalUrl->value }}">
    <meta property="og:title" content="{{ $document->head->openGraphTitle }}">
    <meta property="og:description" content="{{ $document->head->openGraphDescription }}">
    <meta property="og:url" content="{{ $document->head->openGraphUrl->value }}">
    <script type="application/ld+json">{!! $document->head->jsonLd() !!}</script>
</head>
<body>
<header>
    <a href="{{ $document->context->url()->value }}">{{ $document->website->header->clinicName }}</a>
    <nav aria-label="Primary navigation">
        @foreach ($document->navigation as $item)
            <a href="{{ $item->url->value }}">{{ $item->label }}</a>
        @endforeach
    </nav>
</header>
<main>
    <h1>{{ $document->website->branding->clinicName }}</h1>
    <a href="{{ $document->bookingDestination->value }}">Book Appointment</a>
</main>
<footer>{{ $document->website->footer->clinicName }}</footer>
</body>
</html>
