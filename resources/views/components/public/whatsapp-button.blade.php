@props(['action', 'buttonStyle' => 'circle'])

@if ($action !== null)
    <a
        class="whatsapp-float whatsapp-float--circle"
        href="{{ $action->value }}"
        target="_blank"
        rel="noopener noreferrer"
        aria-label="Message the clinic on WhatsApp (opens in a new tab)"
    >
        <x-public.icon name="message" />
    </a>
@endif
