<x-public.booking.layout title="Choose a date" :theme="$theme">
    <x-public.booking.step-header title="When would you like to come in?" :step-number="$viewModel->stepNumber" :total-steps="$viewModel->totalSteps" />
    <p class="booking-intro">Choose a date first. Available times will appear below.</p>

    <form method="POST" action="{{ route('public-website.booking.date.update') }}" data-booking-step-form>
        @csrf
        <div class="booking-chip-grid booking-date-grid" role="radiogroup" aria-label="Choose a date">
            @foreach ($viewModel->dates as $date)
                <label>
                    <input type="radio" name="appointment_date" value="{{ $date->value }}" class="visually-hidden-radio"
                        @checked($date->selected) @disabled(! $date->tappable()) required>
                    <span class="booking-chip" aria-pressed="{{ $date->selected ? 'true' : 'false' }}"
                        @if (! $date->tappable()) aria-disabled="true" @endif>{{ $date->label }}</span>
                </label>
            @endforeach
        </div>

        @if (! $viewModel->hasAnyAvailableDate())
            <p class="booking-empty-state">No available dates in the next two weeks. Please contact the clinic to arrange a time.</p>
        @endif
        @error('appointment_date')<p class="booking-field-error">{{ $message }}</p>@enderror

        <div class="booking-form-actions">
            <a class="booking-secondary-action" href="{{ route('public-website.booking.service') }}">Back</a>
            <button type="submit" name="intent" value="load_times" class="button button--primary" data-booking-submit data-pending-label="Checking…">View available times</button>
        </div>
    </form>

    @if ($timeViewModel !== null)
        <section class="booking-time-selection" aria-labelledby="booking-time-heading">
            <h2 id="booking-time-heading">Choose a time</h2>
            @if ($timeViewModel->hasNoAvailableTimes())
                <p class="booking-empty-state">All appointment slots on this date are full. Please choose another date.</p>
            @else
                <p class="booking-time-selection__hint">Times shown use the clinic's local time.</p>
                <form method="POST" action="{{ route('public-website.booking.date.update') }}" data-booking-step-form>
                    @csrf
                    <input type="hidden" name="appointment_date" value="{{ $timeViewModel->selectedDate }}">
                    <div class="booking-chip-grid booking-time-grid" role="radiogroup" aria-label="Choose a time">
                        @foreach ($timeViewModel->times as $time)
                            <label>
                                <input type="radio" name="appointment_time" value="{{ $time->value }}" class="visually-hidden-radio"
                                    @checked($time->selected) @disabled(! $time->tappable()) required>
                                <span class="booking-chip" aria-pressed="{{ $time->selected ? 'true' : 'false' }}"
                                    @if (! $time->tappable()) aria-disabled="true" @endif>{{ $time->label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('appointment_time')<p class="booking-field-error">{{ $message }}</p>@enderror

                    <div class="booking-form-actions">
                        <span></span>
                        <button type="submit" name="intent" value="continue" class="button button--primary" data-booking-submit data-pending-label="Loading…">Continue</button>
                    </div>
                </form>
            @endif
        </section>
    @endif
</x-public.booking.layout>
