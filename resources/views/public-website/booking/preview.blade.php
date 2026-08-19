<x-public.booking.layout title="Book an Appointment" :theme="$theme">
    @php($bookingSuccess = session('booking_preview_success'))

    <div class="booking-step-header">
        <p class="booking-progress">Booking Preview</p>
        @if (is_array($bookingSuccess))
            <h1>Booking received</h1>
            <p>Your appointment request has been sent to the clinic.</p>
        @else
            <h1>Book an appointment</h1>
            <p>This protected form uses the clinic's real services, operating hours and available capacity.</p>
        @endif
    </div>

    @if (is_array($bookingSuccess))
        <div class="booking-success-card" role="status">
            <p>Booking reference</p>
            <p class="booking-success-reference">{{ $bookingSuccess['reference'] }}</p>
            <p><strong>Status:</strong> {{ $bookingSuccess['status'] === 'submitted' ? 'Received — pending clinic confirmation' : ucfirst($bookingSuccess['status']) }}</p>
        </div>

        <dl class="booking-review-list">
            <div class="booking-review-row">
                <dt>Patient</dt>
                <dd>{{ $bookingSuccess['patient_name'] }}</dd>
            </div>
            @if ($bookingSuccess['service_name'] !== null)
                <div class="booking-review-row">
                    <dt>Service</dt>
                    <dd>{{ $bookingSuccess['service_name'] }}</dd>
                </div>
            @endif
            <div class="booking-review-row">
                <dt>Appointment date</dt>
                <dd>{{ \Carbon\CarbonImmutable::createFromFormat('Y-m-d', $bookingSuccess['appointment_date'])->format('d M Y') }}</dd>
            </div>
            <div class="booking-review-row">
                <dt>Appointment time</dt>
                <dd>{{ \Carbon\CarbonImmutable::createFromFormat('H:i', $bookingSuccess['appointment_time'])->format('g:i A') }}</dd>
            </div>
        </dl>

        <p>The clinic will contact the patient if there are any changes to this appointment.</p>

        <div class="booking-form-actions booking-form-actions--success">
            <a class="button button--primary" href="{{ $backUrl }}">Return to Website preview</a>
        </div>
    @else
        <form method="GET" action="{{ $submitUrl }}" class="booking-fieldset">
        @if ($configuration->serviceSelectionEnabled)
            <fieldset>
                <legend class="booking-label">Choose a service</legend>
                <div class="booking-option-list">
                    @foreach ($configuration->services as $service)
                        <label class="booking-option">
                            <input
                                type="radio"
                                name="service_id"
                                value="{{ $service->id }}"
                                @checked($selectedServiceId === $service->id)
                                @required($configuration->serviceSelectionRequired)
                            >
                            {{ $service->name }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endif
        <div>
            <label class="booking-label" for="appointment_date_lookup">Appointment date</label>
            <input
                class="booking-input"
                id="appointment_date_lookup"
                name="appointment_date"
                type="date"
                min="{{ now()->format('Y-m-d') }}"
                value="{{ $selectedDate }}"
                required
            >
        </div>
        <button class="button button--secondary" type="submit">Check available times</button>
        </form>

        <form method="POST" action="{{ $submitUrl }}" class="booking-fieldset" data-booking-form>
        @csrf
        <input type="hidden" name="submission_token" value="{{ $submissionToken }}">
        <input type="hidden" name="appointment_date" value="{{ $selectedDate }}">
        <input type="hidden" name="service_id" value="{{ $selectedServiceId }}">

        <fieldset>
            <legend class="booking-label">Available time</legend>
            @if ($selectedDate === null)
                <p>Choose a date first to see available times.</p>
            @elseif ($slots === [])
                <p>No appointment times are available on this date. Please choose another date.</p>
            @else
                <div class="booking-option-list">
                    @foreach ($slots as $slot)
                        <label class="booking-option">
                            <input
                                type="radio"
                                name="appointment_time"
                                value="{{ $slot->localStart }}"
                                @checked(old('appointment_time') === $slot->localStart)
                                required
                            >
                            {{ \Carbon\CarbonImmutable::createFromFormat('H:i', $slot->localStart)->format('g:i A') }}
                        </label>
                    @endforeach
                </div>
            @endif
        </fieldset>

        <div>
            <label class="booking-label" for="patient_name">Patient name</label>
            <input class="booking-input" id="patient_name" name="patient_name" type="text" value="{{ old('patient_name') }}" maxlength="200" required autocomplete="name">
        </div>
        <div>
            <label class="booking-label" for="phone">Phone number</label>
            <input class="booking-input" id="phone" name="phone" type="tel" value="{{ old('phone') }}" maxlength="40" required autocomplete="tel">
        </div>
        @if ($configuration->emailEnabled)
            <div>
                <label class="booking-label" for="email">Email (optional)</label>
                <input class="booking-input" id="email" name="email" type="email" value="{{ old('email') }}" maxlength="254" autocomplete="email">
            </div>
        @endif
        @if ($configuration->notesEnabled)
            <div>
                <label class="booking-label" for="notes">Notes (optional)</label>
                <textarea class="booking-input" id="notes" name="notes" maxlength="1000">{{ old('notes') }}</textarea>
            </div>
        @endif
        <label class="booking-option">
            <input type="checkbox" name="consent" value="1" required>
            I agree that the clinic may contact me about this appointment.
        </label>

        <div class="booking-sticky-actions">
            <a class="button button--secondary" href="{{ $backUrl }}">Return to Website preview</a>
            <button class="button button--primary" type="submit" @disabled($selectedDate === null || $slots === []) data-submit-booking>Submit booking</button>
        </div>
        </form>
    @endif

</x-public.booking.layout>
