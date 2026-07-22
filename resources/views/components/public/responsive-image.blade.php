@props(['url', 'alt' => '', 'width' => null, 'height' => null, 'priority' => false, 'class' => ''])
<img
    class="responsive-image {{ $class }}"
    src="{{ $url }}"
    alt="{{ $alt }}"
    @if ($width !== null) width="{{ $width }}" @endif
    @if ($height !== null) height="{{ $height }}" @endif
    loading="{{ $priority ? 'eager' : 'lazy' }}"
    decoding="async"
    @if ($priority) fetchpriority="high" @endif
>
