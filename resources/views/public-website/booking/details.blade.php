<x-public.booking.layout title="Your details" :theme="$theme">
    <x-public.booking.step-header title="Your details" :step-number="$viewModel->stepNumber" :total-steps="$viewModel->totalSteps" />
    <p class="booking-intro">Tell the clinic who the appointment is for and how to contact you.</p>

    <form method="POST" action="{{ route('public-website.booking.details.update') }}" data-booking-step-form>
        @csrf
        <div class="booking-fieldset">
            <div class="booking-field">
                <label class="booking-label" for="patient_name">Full name</label>
                <input class="booking-input" type="text" id="patient_name" name="patient_name" value="{{ $viewModel->patientName }}" autocomplete="name" required>
                @error('patient_name')<p class="booking-field-error">{{ $message }}</p>@enderror
            </div>
            <div class="booking-field">
                <label class="booking-label" for="phone">Phone number</label>
                <input class="booking-input" type="tel" inputmode="tel" id="phone" name="phone" value="{{ $viewModel->phone }}" autocomplete="tel" required>
                @error('phone')<p class="booking-field-error">{{ $message }}</p>@enderror
            </div>
            @if ($viewModel->emailEnabled)
                <div class="booking-field booking-field--wide">
                    <label class="booking-label" for="email">Email (optional)</label>
                    <input class="booking-input" type="email" id="email" name="email" value="{{ $viewModel->email }}" autocomplete="email">
                    @error('email')<p class="booking-field-error">{{ $message }}</p>@enderror
                </div>
            @endif
            @if ($viewModel->notesEnabled)
                <div class="booking-field booking-field--wide">
                    <label class="booking-label" for="notes">Anything you'd like us to know? (optional)</label>
                    <textarea class="booking-input" id="notes" name="notes">{{ $viewModel->notes }}</textarea>
                </div>
            @endif
            <div class="booking-consent-card booking-field--wide">
                <label class="booking-consent">
                    <input class="booking-consent__input" type="checkbox" name="consent" value="1" @checked($viewModel->consent) required>
                    <span class="booking-consent__box" aria-hidden="true"></span>
                    <span>I agree to be contacted about this booking.</span>
                </label>
                @error('consent')<p class="booking-field-error">{{ $message }}</p>@enderror
                <p class="booking-privacy-note">Your information is only used to manage your appointment.</p>
            </div>
        </div>

        <div class="booking-form-actions">
            <a class="booking-secondary-action" href="{{ route('public-website.booking.date') }}">Back</a>
            <button type="submit" class="button button--primary" data-booking-submit data-pending-label="Saving…">Continue</button>
        </div>
    </form>
</x-public.booking.layout>
