@php
    $defaultCanonical = $context->url('/blog/'.$post->slug)->value;
    $candidateHost = $post->canonical_url ? parse_url($post->canonical_url, PHP_URL_HOST) : null;
    $canonical = is_string($candidateHost) && hash_equals(explode(':', $context->host, 2)[0], strtolower($candidateHost)) ? $post->canonical_url : $defaultCanonical;
    $imagePath = $post->featured_image_asset_id ? route('public-website.assets.show', $post->featured_image_asset_id, false) : null;
    $image = $imagePath ? $context->origin().$imagePath : null;
    $landingUrl = $homeUrl ?? '/';
    $landingBlogUrl = rtrim($landingUrl, '#').'#blog';
    $encodedShareUrl = rawurlencode($canonical);
    $encodedShareText = rawurlencode($post->title.' — '.$canonical);
    $article = ['@context'=>'https://schema.org','@type'=>'Article','headline'=>$post->title,'description'=>$post->meta_description,'datePublished'=>$post->published_at,'dateModified'=>$post->last_changed_at,'author'=>['@type'=>'Person','name'=>$post->author_name],'publisher'=>['@type'=>'Organization','name'=>$website->clinic_name],'mainEntityOfPage'=>$canonical];
    if ($image) $article['image'] = [$image];
    $breadcrumbs = ['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>[['@type'=>'ListItem','position'=>1,'name'=>'Home','item'=>$context->url('/')->value],['@type'=>'ListItem','position'=>2,'name'=>'Blog','item'=>$context->url('/blog')->value],['@type'=>'ListItem','position'=>3,'name'=>$post->title,'item'=>$canonical]]];
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $post->meta_title }}</title>
    <meta name="description" content="{{ $post->meta_description }}">
    <meta name="robots" content="{{ ($isPreview ?? false) ? 'noindex,nofollow,noarchive' : (($indexingEnabled ?? true) ? $post->robots_directive : 'noindex,nofollow') }}">
    <link rel="canonical" href="{{ $canonical }}">
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="{{ $website->clinic_name }}">
    <meta property="og:title" content="{{ $post->open_graph_title }}">
    <meta property="og:description" content="{{ $post->open_graph_description }}">
    <meta property="og:url" content="{{ $canonical }}">
    @if($image)<meta property="og:image" content="{{ $image }}"><meta property="og:image:alt" content="{{ $post->featured_image_alt_text }}">@endif
    <meta name="twitter:card" content="{{ $image ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $post->open_graph_title }}">
    <meta name="twitter:description" content="{{ $post->open_graph_description }}">
    <script type="application/ld+json">{!! json_encode($article, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
    <script type="application/ld+json">{!! json_encode($breadcrumbs, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
    @unless(app()->environment('testing')) @vite(['resources/css/public-website.css', 'resources/js/public-website.js', 'resources/js/public-content-enhancements.js']) @endunless
</head>
<body class="public-site blog-page" data-template="{{ strtolower(str_replace('_', '-', $website->template_id)) }}">
    <a class="skip-link" href="#article-content">Skip to article</a>
    <header class="blog-page-header">
        <nav class="public-container blog-page-nav" aria-label="Article navigation">
            <a class="blog-page-brand" href="{{ $landingUrl }}">{{ $website->clinic_name }}</a>
            <a class="blog-page-back" href="{{ $landingBlogUrl }}"><span aria-hidden="true">←</span> All articles</a>
        </nav>
    </header>

    <main id="article-content" class="blog-article">
        <header class="blog-article__header public-container public-container--reading">
            <a class="blog-article__breadcrumb" href="{{ $landingBlogUrl }}"><span aria-hidden="true">←</span> Back to articles</a>
            <div class="blog-article__meta">
                <span class="blog-card-category">{{ $post->category }}</span>
                <time datetime="{{ $post->published_at }}">{{ \Carbon\Carbon::parse($post->published_at)->locale('ms')->translatedFormat('j F Y') }}</time>
            </div>
            <h1>{{ $post->title }}</h1>
            <p class="blog-article__lead">{{ $post->excerpt }}</p>
            <p class="blog-article__byline">By <strong>{{ $post->author_name }}</strong></p>
        </header>

        @if($imagePath)
            <figure class="blog-article__media public-container public-container--reading">
                <img src="{{ $imagePath }}" alt="{{ $post->featured_image_alt_text }}" loading="eager" fetchpriority="high">
            </figure>
        @endif

        <div class="blog-prose public-container public-container--reading">{!! $post->body_html !!}</div>

        <aside class="blog-share public-container public-container--reading" aria-labelledby="blog-share-title" data-blog-share>
            <div class="blog-share__intro">
                <span class="blog-share__eyebrow">Share this article</span>
                <h2 id="blog-share-title">Know someone who may find this helpful?</h2>
            </div>
            <div class="blog-share__actions" aria-label="Article sharing options">
                <a href="https://wa.me/?text={{ $encodedShareText }}" target="_blank" rel="noopener noreferrer" aria-label="Share article via WhatsApp">WhatsApp</a>
                <a href="https://www.facebook.com/sharer/sharer.php?u={{ $encodedShareUrl }}" target="_blank" rel="noopener noreferrer" aria-label="Share article via Facebook">Facebook</a>
                <a href="https://twitter.com/intent/tweet?text={{ rawurlencode($post->title) }}&amp;url={{ $encodedShareUrl }}" target="_blank" rel="noopener noreferrer" aria-label="Share article via X">X</a>
                <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ $encodedShareUrl }}" target="_blank" rel="noopener noreferrer" aria-label="Share article via LinkedIn">LinkedIn</a>
                <button type="button" data-blog-copy-link data-share-url="{{ $canonical }}">Copy link</button>
            </div>
            <p class="blog-share__status" role="status" aria-live="polite" data-blog-share-status></p>
        </aside>

        <aside class="blog-cta public-container public-container--reading" aria-labelledby="blog-cta-title">
            <div>
                <span class="blog-cta__eyebrow">We are here to help</span>
                <h2 id="blog-cta-title">Need advice tailored to your needs?</h2>
                <p>Speak with the {{ $website->clinic_name }} team for personalised guidance.</p>
            </div>
            <a href="{{ $bookingUrl ?? '/booking' }}">Book an appointment</a>
        </aside>
    </main>
</body>
</html>
