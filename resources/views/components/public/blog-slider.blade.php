@props([
    'articles',
    'articleUrlPrefix' => '/blog',
    'language' => 'en',
])

@if($articles->isNotEmpty())
    @php
        $articleCount = $articles->count();
    @endphp
    <section
        id="blog"
        class="blog-slider public-section anchor-section {{ $articleCount === 1 ? 'blog-slider--single' : '' }}"
        aria-labelledby="blog-slider-title"
        data-blog-slider
        data-blog-slider-count="{{ $articleCount }}"
    >
        <div class="public-container">
            <div class="blog-slider__header">
                <div class="blog-slider__heading">
                    <span class="blog-slider__eyebrow">{{ $language === 'ms' ? 'Info klinik' : 'Clinic insights' }}</span>
                    <h2 id="blog-slider-title">{{ $language === 'ms' ? 'Artikel kesihatan terkini' : 'Latest health articles' }}</h2>
                    <p>{{ $language === 'ms' ? 'Panduan kesihatan praktikal daripada pasukan klinikal kami untuk anda dan keluarga.' : 'Practical health guidance from our clinical team for you and your family.' }}</p>
                </div>
                @if($articleCount > 1)
                <div class="blog-slider__controls" aria-label="{{ $language === 'ms' ? 'Kawalan artikel' : 'Article slider controls' }}">
                    <span class="blog-slider__status" aria-live="polite" data-blog-slider-status></span>
                    <button type="button" aria-label="{{ $language === 'ms' ? 'Artikel sebelumnya' : 'Previous article' }}" data-blog-slider-previous>
                        <span aria-hidden="true">←</span>
                    </button>
                    <button type="button" aria-label="{{ $language === 'ms' ? 'Artikel seterusnya' : 'Next article' }}" data-blog-slider-next>
                        <span aria-hidden="true">→</span>
                    </button>
                </div>
                @endif
            </div>

            <div class="blog-slider__viewport" tabindex="0" data-blog-slider-viewport>
                <div class="blog-slider__track">
                    @foreach($articles as $article)
                        @php
                            $articleUrl = $article->url ?? rtrim($articleUrlPrefix, '/').'/'.$article->slug;
                            $imageUrl = $article->image_url ?? ($article->featured_image_asset_id ? '/assets/'.$article->featured_image_asset_id : null);
                        @endphp
                        <article class="blog-card">
                            @if($imageUrl)
                                <a href="{{ $articleUrl }}" class="blog-card-image" tabindex="-1">
                                    <img src="{{ $imageUrl }}" alt="{{ $article->featured_image_alt_text }}" loading="lazy">
                                </a>
                            @endif
                            <div class="blog-card-body">
                                <div class="blog-card-meta-row">
                                    <span class="blog-card-category">{{ $article->category }}</span>
                                    @if(isset($article->published_at))
                                        <time datetime="{{ $article->published_at }}">{{ \Carbon\Carbon::parse($article->published_at)->locale('ms')->translatedFormat('j M Y') }}</time>
                                    @endif
                                </div>
                                <h3><a href="{{ $articleUrl }}">{{ $article->title }}</a></h3>
                                <p class="blog-card-excerpt">{{ $article->excerpt }}</p>
                                <a class="blog-card-read-more" href="{{ $articleUrl }}">{{ $language === 'ms' ? 'Baca artikel' : 'Read article' }} <span aria-hidden="true">→</span></a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>

        </div>
    </section>
@endif
