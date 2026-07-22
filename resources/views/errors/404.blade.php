<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Page not found</title>
    @unless (app()->environment('testing')) @vite('resources/js/public-website.js') @endunless
</head>
<body class="public-site">
    <main class="error-document">
        <div class="public-container public-container--reading">
            <p class="eyebrow">404</p>
            <h1>We couldn’t find that page.</h1>
            <p>The address may have changed, or the page may no longer be available.</p>
            <a class="button button--primary" href="/">Return home</a>
        </div>
    </main>
</body>
</html>
