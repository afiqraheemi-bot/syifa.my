<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index,follow">
    <title>{{ $document->title }}</title>
    @unless (app()->environment('testing')) @vite(['resources/css/public-website.css', 'resources/js/public-website.js']) @endunless
</head>
<body class="public-site">
    <x-public.skip-link />
    <header class="legal-header"><div class="public-container"><a href="/">SYIFA.my</a></div></header>
    <main id="main-content" class="legal-document">
        <article class="public-container public-container--reading">
            <p class="eyebrow">Platform policy</p>
            <h1>{{ $document->title }}</h1>
            <p class="legal-document__version">Version {{ $document->version }}</p>
            <div class="legal-document__copy">
                @foreach ($document->paragraphs as $paragraph)<p>{{ $paragraph }}</p>@endforeach
            </div>
            <a class="text-action" href="/">Return home <span aria-hidden="true">→</span></a>
        </article>
    </main>
</body>
</html>
