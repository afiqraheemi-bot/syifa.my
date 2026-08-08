<x-public.booking.layout title="Book your appointment" :theme="$theme">
    <x-public.booking.step-header title="Book your appointment" :step-number="$viewModel->stepNumber" :total-steps="$viewModel->totalSteps" />

    <p>Just a few quick steps.</p>

    <div class="booking-sticky-actions">
        <a class="button button--primary" href="{{ route('public-website.booking.service') }}">Start Booking</a>
        @if ($viewModel->whatsAppUrl !== null)
            <p><a class="text-action" href="{{ $viewModel->whatsAppUrl }}">Prefer to message us?</a></p>
        @endif
    </div>
</x-public.booking.layout>
