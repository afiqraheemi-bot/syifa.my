<x-public.booking.layout title="Your details">
    <x-public.booking.step-header title="Your details" :step-number="$viewModel->stepNumber" :total-steps="$viewModel->totalSteps" />

    <form method="POST" action="{{ route('public-website.booking.details.update') }}">
        @csrf
        <div class="booking-fieldset">
            <div>
                <label class="booking-label" for="patient_name">Full name</label>
                <input class="booking-input" type="text" id="patient_name" name="patient_name" value="{{ $viewModel->patientName }}" required>
                @error('patient_name')<p class="booking-field-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="booking-label" for="phone">Phone number</label>
                <input class="booking-input" type="tel" inputmode="tel" id="phone" name="phone" value="{{ $viewModel->phone }}" required>
                @error('phone')<p class="booking-field-error">{{ $message }}</p>@enderror
            </div>
            @if ($viewModel->emailEnabled)
                <div>
                    <label class="booking-label" for="email">Email (optional)</label>
                    <input class="booking-input" type="email" id="email" name="email" value="{{ $viewModel->email }}">
                    @error('email')<p class="booking-field-error">{{ $message }}</p>@enderror
                </div>
            @endif
            @if ($viewModel->notesEnabled)
                <div>
                    <label class="booking-label" for="notes">Anything you'd like us to know? (optional)</label>
                    <textarea class="booking-input" id="notes" name="notes">{{ $viewModel->notes }}</textarea>
                </div>
            @endif
            <div class="booking-option">
                <label>
                    <input type="checkbox" name="consent" value="1" @checked($viewModel->consent)>
                    I agree to be contacted about this booking.
                </label>
                @error('consent')<p class="booking-field-error">{{ $message }}</p>@enderror
                <p>Your information is only used to manage your appointment.</p>
            </div>
        </div>

        <div class="booking-sticky-actions">
            <button type="submit" class="button button--primary">Continue</button>
        </div>
    </form>
</x-public.booking.layout>
