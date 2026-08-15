<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Health Blog | {{ $website->clinic_name }}</title>
    <meta name="description" content="Latest health articles from {{ $website->clinic_name }}.">
    <meta name="robots" content="{{ ($isPreview ?? false) ? 'noindex,nofollow,noarchive' : (($indexingEnabled ?? true) ? 'index,follow' : 'noindex,nofollow') }}"><link rel="canonical" href="{{ $context->url('/blog')->value }}">
    <meta property="og:type" content="website"><meta property="og:title" content="Health Blog | {{ $website->clinic_name }}">
    <meta property="og:url" content="{{ $context->url('/blog')->value }}">
    @unless(app()->environment('testing')) @vite(['resources/css/public-website.css']) @endunless
</head>
<body class="public-site" data-template="{{ strtolower(str_replace('_', '-', $website->template_id)) }}">
<header class="public-header"><nav class="public-container" aria-label="Main navigation"><a href="{{ $homeUrl ?? '/' }}">{{ $website->clinic_name }}</a><a href="{{ $blogIndexUrl ?? '/blog' }}" aria-current="page">Blog</a></nav></header>
<main id="main-content" class="public-container blog-listing">
    <header><p>Insights from our clinical team</p><h1>Health Articles</h1></header>
    <div class="blog-grid">
        @forelse($posts as $post)
            <article class="blog-card">
                @if($post->featured_image_asset_id)
                    <a href="{{ ($articleUrlPrefix ?? '/blog').'/'.$post->slug }}" class="blog-card-image" tabindex="-1"><img src="/assets/{{ $post->featured_image_asset_id }}" alt="{{ $post->featured_image_alt_text }}" loading="lazy"></a>
                @endif
                <div class="blog-card-body"><p>{{ $post->category }}</p><h2><a href="{{ ($articleUrlPrefix ?? '/blog').'/'.$post->slug }}">{{ $post->title }}</a></h2><p>{{ $post->excerpt }}</p><p class="blog-card-meta"><small>{{ $post->author_name }} · <time datetime="{{ $post->published_at }}">{{ \Carbon\Carbon::parse($post->published_at)->locale('ms')->translatedFormat('j M Y') }}</time></small></p></div>
            </article>
        @empty
            <section aria-labelledby="empty-title"><h2 id="empty-title">New articles are coming soon</h2><p>Visit again for practical health guidance from our clinical team.</p></section>
        @endforelse
    </div>
    {{ $posts->links() }}
</main>
</body></html>
