<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document->title }}</title>
</head>
<body>
<main>
    <h1>{{ $document->title }}</h1>
    @foreach ($document->paragraphs as $paragraph)
        <p>{{ $paragraph }}</p>
    @endforeach
    <p>Version {{ $document->version }}</p>
</main>
</body>
</html>
