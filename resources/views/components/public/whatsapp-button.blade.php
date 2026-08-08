@props(['action', 'buttonStyle' => 'pill'])

@php
    $resolvedStyle = in_array($buttonStyle, ['pill', 'circle', 'rounded_square'], true)
        ? $buttonStyle
        : 'pill';
    $iconOnly = $resolvedStyle !== 'pill';
@endphp

@if ($action !== null)
    <a
        class="whatsapp-float whatsapp-float--{{ str_replace('_', '-', $resolvedStyle) }}"
        href="{{ $action->value }}"
        target="_blank"
        rel="noopener noreferrer"
        aria-label="Message the clinic on WhatsApp (opens in a new tab)"
    >
        <x-public.icon name="message" />
        @unless ($iconOnly)
            <span>WhatsApp clinic</span>
        @endunless
    </a>
@endif
