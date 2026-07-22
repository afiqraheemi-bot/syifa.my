@props(['eyebrow' => null, 'title', 'description' => null, 'align' => 'start'])
<div class="section-heading section-heading--{{ $align }}">
    @if ($eyebrow !== null)<p class="eyebrow">{{ $eyebrow }}</p>@endif
    <h2>{{ $title }}</h2>
    @if ($description !== null)<p>{{ $description }}</p>@endif
</div>
