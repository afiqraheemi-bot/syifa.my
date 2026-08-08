<x-public.booking.layout title="Review your booking" :theme="$theme">
    <x-public.booking.step-header title="Review your booking" :step-number="$viewModel->stepNumber" :total-steps="$viewModel->totalSteps" />
    <p class="booking-intro">Check the details below before sending your appointment request.</p>

    <dl class="booking-review-list">
        @if ($viewModel->serviceLabel !== null)
            <div class="booking-review-row">
                <dt>Service</dt>
                <dd>{{ $viewModel->serviceLabel }} <a href="{{ route('public-website.booking.service') }}">Edit</a></dd>
            </div>
        @endif
        <div class="booking-review-row">
            <dt>Date &amp; time</dt>
            <dd>{{ $viewModel->date }} at {{ $viewModel->time }} <a href="{{ route('public-website.booking.date') }}">Edit</a></dd>
        </div>
        <div class="booking-review-row">
            <dt>Name</dt>
            <dd>{{ $viewModel->patientName }} <a href="{{ route('public-website.booking.details') }}">Edit</a></dd>
        </div>
        <div class="booking-review-row">
            <dt>Phone</dt>
            <dd>{{ $viewModel->phone }} <a href="{{ route('public-website.booking.details') }}">Edit</a></dd>
        </div>
        @if ($viewModel->email !== null)
            <div class="booking-review-row">
                <dt>Email</dt>
                <dd>{{ $viewModel->email }}</dd>
            </div>
        @endif
        @if ($viewModel->notes !== null)
            <div class="booking-review-row">
                <dt>Notes</dt>
                <dd>{{ $viewModel->notes }}</dd>
            </div>
        @endif
    </dl>

    <form method="POST" action="{{ route('public-website.booking.submit') }}" data-booking-step-form>
        @csrf
        <input type="hidden" name="submission_token" value="{{ $submissionToken }}">
        <div class="booking-form-actions">
            <a class="booking-secondary-action" href="{{ route('public-website.booking.details') }}">Back</a>
            <button type="submit" class="button button--primary" data-booking-submit data-pending-label="Confirming…">Confirm Booking</button>
        </div>
    </form>
</x-public.booking.layout>
