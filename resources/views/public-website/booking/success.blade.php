<x-public.booking.layout title="Booking received">
    <h1>Your booking request has been received</h1>

    <div class="booking-success-card">
        <p>Booking reference</p>
        <p class="booking-success-reference">{{ $viewModel->reference }}</p>
        <p>Status: {{ $viewModel->statusLabel }} — submitted {{ $viewModel->submittedAtLabel }}</p>
        <p>The clinic will be in touch if anything changes.</p>
    </div>

    <div class="booking-sticky-actions">
        <a class="button button--primary" href="{{ route('public-website.home') }}">Return Home</a>
        @if ($viewModel->whatsAppUrl !== null)
            <p><a class="text-action" href="{{ $viewModel->whatsAppUrl }}">Need to change something? Message us on WhatsApp</a></p>
        @endif
    </div>
</x-public.booking.layout>
