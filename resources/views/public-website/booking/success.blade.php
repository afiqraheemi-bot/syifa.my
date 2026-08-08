<x-public.booking.layout title="Booking received" :theme="$theme">
    <p class="booking-success-kicker">Request received</p>
    <h1>Your appointment request has been sent</h1>
    <p class="booking-intro">Keep this reference for your records. The clinic can now review your request.</p>

    <div class="booking-success-card">
        <p>Appointment reference</p>
        <p class="booking-success-reference">{{ $viewModel->reference }}</p>
        <p>Status: {{ $viewModel->statusLabel }} — submitted {{ $viewModel->submittedAtLabel }}</p>
        <p>The clinic will be in touch if anything changes.</p>
    </div>

    <div class="booking-form-actions booking-form-actions--success">
        <a class="button button--primary" href="{{ route('public-website.home') }}">Return Home</a>
        @if ($viewModel->whatsAppUrl !== null)
            <p><a class="text-action" href="{{ $viewModel->whatsAppUrl }}">Need to change something? Message us on WhatsApp</a></p>
        @endif
    </div>
</x-public.booking.layout>
