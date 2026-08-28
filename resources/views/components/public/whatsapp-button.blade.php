@props(['action', 'buttonStyle' => 'circle', 'language' => 'en'])

@if ($action !== null)
    <a
        class="whatsapp-float whatsapp-float--circle"
        href="{{ $action->value }}"
        target="_blank"
        rel="noopener noreferrer"
        aria-label="{{ $language === 'ms' ? 'Mesej klinik melalui WhatsApp (dibuka dalam tab baharu)' : 'Message the clinic on WhatsApp (opens in a new tab)' }}"
    >
        <x-public.icon name="message" />
    </a>
@endif
